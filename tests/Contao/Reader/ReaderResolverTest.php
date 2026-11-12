<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Contao\Reader;

use Contao\Model\Registry;
use Contao\TestCase\ContaoTestCase;
use HeimrichHannot\FlareBundle\Config\ComponentConfig;
use HeimrichHannot\FlareBundle\Contao\Reader\ReaderResolver;
use HeimrichHannot\FlareBundle\Event\ReaderPageMetaEvent;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\ControlBinding;
use HeimrichHannot\FlareBundle\Filter\ControlContext;
use HeimrichHannot\FlareBundle\Filter\Control\FilterControlInterface;
use HeimrichHannot\FlareBundle\Filter\ElementBinding;
use HeimrichHannot\FlareBundle\Filter\ElementContext;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\FilterCompound;
use HeimrichHannot\FlareBundle\Filter\FilterEffectBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Value\KeywordsValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Form\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\List\Entry\ModelRef;
use HeimrichHannot\FlareBundle\List\FilterFailure;
use HeimrichHannot\FlareBundle\Tests\Engine\EnginePipelineTrait;
use HeimrichHannot\FlareBundle\Tests\Filter\Element\StubsContaoDcaTrait;
use HeimrichHannot\FlareBundle\Tests\Fixture\ContentModelFixture;
use HeimrichHannot\FlareBundle\Tests\Fixture\ListModelFixture;
use HeimrichHannot\FlareBundle\Tests\Fixture\TestModelFixture;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Resolves the reader element 5 of a list on `tl_test` through the real pipeline and an SQLite table.
 */
final class ReaderResolverTest extends ContaoTestCase
{
    use EnginePipelineTrait;
    use StubsContaoDcaTrait;

    /** Row 1 published, row 2 not. */
    private const ROWS = [
        ['id' => 1, 'alias' => 'visible', 'published' => 1],
        ['id' => 2, 'alias' => 'hidden', 'published' => 0],
    ];

    /** @var list<TestModelFixture> */
    private array $registered = [];

    /** @var array<string, class-string>|null */
    private ?array $models = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stubTestDca(['id', 'alias', 'title', 'published']);

        // Registry::fetch() needs the model class of the table.
        $this->models = $GLOBALS['TL_MODELS'] ?? null;
        $GLOBALS['TL_MODELS']['tl_test'] = TestModelFixture::class;
    }

    protected function tearDown(): void
    {
        $registry = Registry::getInstance();

        foreach ($this->registered as $model) {
            $registry->unregisterAlias($model, 'alias', $model->alias);
            $registry->unregister($model);
        }

        $this->registered = [];

        if ($this->models === null) {
            unset($GLOBALS['TL_MODELS']);
        } else {
            $GLOBALS['TL_MODELS'] = $this->models;
        }

        parent::tearDown();
    }

    /**
     * @dataProvider provideRegistryKeys
     */
    public function testAnEntryOutsideTheListIsNotFoundEvenIfTheRegistryHoldsIt(
        string $autoItemField,
        string $autoItem,
    ): void {
        if ($autoItemField === 'id') {
            // without an `alias` field, the auto-item field falls back to `id`
            $this->stubTestDca(['id', 'title', 'published']);
        }

        $registered = $this->register(new TestModelFixture(['id' => 2, 'alias' => 'hidden', 'published' => '1']));
        self::assertSame($registered, Registry::getInstance()->fetch('tl_test', 2), 'Precondition.');
        self::assertSame($registered, Registry::getInstance()->fetch('tl_test', 'hidden', 'alias'), 'Precondition.');

        $resolver = $this->resolver(autoItem: $autoItem, filters: [self::publishedOnly()], content: $this->content());

        $forPage = $resolver->resolveForPage();
        self::assertNotNull($forPage, 'The breadcrumb path finds the reader.');
        self::assertNull($forPage->entry);

        $resolution = $resolver->resolve($this->content());
        self::assertNull($resolution->entry);
        self::assertNull($resolution->failure);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideRegistryKeys(): iterable
    {
        yield 'by alias' => ['alias', 'hidden'];
        yield 'by id' => ['id', '2'];
    }

    public function testARegisteredUnpublishedInstanceDoesNotCrashTheReader(): void
    {
        // The old reader built a second model for a registered id: "already contains an instance".
        $this->register(new TestModelFixture(['id' => 1, 'alias' => 'visible', 'published' => '']));

        $entry = $this->resolver(autoItem: 'visible')->resolve($this->content())->entry;

        self::assertSame(1, (int) $entry?->row['id']);
    }

    public function testAnEntryInsideTheListIsResolved(): void
    {
        $resolution = $this->resolver(autoItem: 'visible', filters: [self::publishedOnly()])->resolve($this->content());

        $entry = $resolution->entry;
        self::assertNotNull($entry);
        self::assertSame('visible', $entry->row['alias']);
        self::assertEquals(new ModelRef('tl_test', 1), $entry->meta->modelRef);
        self::assertNull($resolution->failure);
        self::assertNotNull($resolution->view);
        self::assertSame($resolution->list, $resolution->engine?->getList());
    }

    public function testAControlThatCannotBuildItsFormDoesNotFailTheReader(): void
    {
        $unbuildable = new FilterCompound(
            element: new ElementBinding(new ReaderPassingElement(), 'test_element', new ComponentConfig([]), null),
            alias: 'q',
            control: new ControlBinding(
                new ReaderUnbuildableControl(),
                ReaderUnbuildableControl::class,
                new ComponentConfig([]),
            ),
        );

        $resolution = $this->resolver(autoItem: 'visible', filters: [self::publishedOnly(), $unbuildable])
            ->resolve($this->content());

        self::assertNotNull($resolution->entry);
        self::assertNull($resolution->failure);
    }

    public function testARecordedFailureFailsTheReaderAndIsMemoized(): void
    {
        $resolver = $this->resolver(
            autoItem: 'visible',
            failures: [new FilterFailure('tl_flare_filter.3', 'broken')],
        );

        $first = $resolver->resolve($this->content());
        self::assertInstanceOf(FilterException::class, $first->failure);
        self::assertNull($first->entry);
        self::assertNull($first->getPageMeta());

        self::assertSame($first, $resolver->resolve($this->content()));
        self::assertSame(1, $this->builds);
    }

    public function testAReaderWithoutAListFails(): void
    {
        $resolution = $this->resolver(autoItem: 'visible')->resolve(new ContentModelFixture(['id' => 5]));

        self::assertInstanceOf(FlareException::class, $resolution->failure);
        self::assertSame('No list model found.', $resolution->failure->getMessage());
        self::assertNull($resolution->list);
        self::assertSame(0, $this->builds);
    }

    public function testTheSameElementTwiceQueriesOnce(): void
    {
        $resolver = $this->resolver(autoItem: 'visible');

        $resolver->resolve($this->content());
        $resolver->resolve($this->content());

        self::assertSame(1, $this->queries);
    }

    public function testBreadcrumbThenReaderShareTheEntryAndReadTheAutoItemOnce(): void
    {
        $resolver = $this->resolver(autoItem: 'visible', content: $this->content());

        $entry = $resolver->resolveForPage()?->entry;

        self::assertNotNull($entry);
        self::assertSame($entry, $resolver->resolve($this->content())->entry);
        self::assertSame(1, $this->autoItemReads);
    }

    public function testPageMetaIsDispatchedOnce(): void
    {
        $dispatches = 0;
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            ReaderPageMetaEvent::class,
            static function (ReaderPageMetaEvent $event) use (&$dispatches): void {
                ++$dispatches;
                $event->pageMeta->setTitle('T');
            },
        );

        $resolution = $this->resolver(autoItem: 'visible', dispatcher: $dispatcher)->resolve($this->content());

        self::assertSame('T', $resolution->getPageMeta()?->getTitle());
        $resolution->getPageMeta();
        self::assertSame(1, $dispatches);
    }

    public function testWithoutAutoItemNothingIsBuiltOrQueried(): void
    {
        $resolver = $this->resolver(autoItem: null, content: $this->content());

        self::assertNull($resolver->resolveForPage());
        self::assertNull($resolver->resolve($this->content())->entry);
        self::assertSame([0, 0], [$this->queries, $this->builds]);
    }

    public function testResetEmptiesTheMemoAndTheAutoItem(): void
    {
        // consecutive reads: two main requests in one worker
        $resolver = $this->resolver(autoItem: ['visible', 'hidden']);

        $first = $resolver->resolve($this->content());
        $resolver->reset();
        $second = $resolver->resolve($this->content());

        self::assertSame(['visible', 'hidden'], [$first->entry?->row['alias'], $second->entry?->row['alias']]);
        self::assertSame(2, $this->autoItemReads);
    }

    /**
     * @param string|list<?string>|null $autoItem
     * @param list<FilterCompound>       $filters
     * @param list<FilterFailure>        $failures
     */
    private function resolver(
        string|array|null    $autoItem,
        array                $filters = [],
        array                $failures = [],
        ?ContentModelFixture $content = null,
        ?EventDispatcher     $dispatcher = null,
    ): ReaderResolver {
        return $this->createReaderResolver(
            $this->createConnection(self::ROWS),
            $dispatcher ?? new EventDispatcher(),
            $autoItem,
            $filters,
            $failures,
            $content,
        );
    }

    /**
     * The reader element 5 of a stored list without filters of its own.
     */
    private function content(): ContentModelFixture
    {
        return new ContentModelFixture(['id' => 5], new ListModelFixture(['id' => 0, 'type' => 'test_list']));
    }

    private function register(TestModelFixture $model): TestModelFixture
    {
        $registry = Registry::getInstance();
        $registry->register($model);
        $registry->registerAlias($model, 'alias', $model->alias);
        $this->registered[] = $model;

        return $model;
    }

    /**
     * @param list<string> $fields
     */
    private function stubTestDca(array $fields): void
    {
        $this->stubDca('tl_test', ['fields' => \array_fill_keys($fields, [])]);
    }
}

/**
 * Restricts nothing.
 */
final class ReaderPassingElement implements FilterElementInterface
{
    public function buildEffect(
        FilterEffectBuilderInterface $builder,
        ElementContext               $context,
        ?ValueInterface              $value,
    ): void {
    }
}

final class ReaderUnbuildableControl implements FilterControlInterface
{
    public function buildForm(FilterFormBuilderInterface $builder, ControlContext $context): void
    {
        throw new FilterException('This control cannot build its form.');
    }

    public function decode(mixed $data, ControlContext $context): ?KeywordsValue
    {
        return null;
    }

    public function hydrate(?ValueInterface $value, ControlContext $context): mixed
    {
        return null;
    }
}
