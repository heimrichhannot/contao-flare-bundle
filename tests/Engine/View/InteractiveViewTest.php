<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Engine\View;

use HeimrichHannot\FlareBundle\Config\ComponentConfig;
use HeimrichHannot\FlareBundle\Engine\Intent\InteractiveIntent;
use HeimrichHannot\FlareBundle\Engine\View\InteractiveView;
use HeimrichHannot\FlareBundle\Enum\SqlEquationOperator;
use HeimrichHannot\FlareBundle\Event\ListDriverEntriesEvent;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\ControlBinding;
use HeimrichHannot\FlareBundle\Filter\ControlContext;
use HeimrichHannot\FlareBundle\Filter\Control\FilterControlInterface;
use HeimrichHannot\FlareBundle\Filter\Control\KeywordsFilterControl;
use HeimrichHannot\FlareBundle\Filter\ElementBinding;
use HeimrichHannot\FlareBundle\Filter\ElementContext;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\FilterCompound;
use HeimrichHannot\FlareBundle\Filter\FilterEffectBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Predicate\SimpleEquationPredicate;
use HeimrichHannot\FlareBundle\Filter\Value\KeywordsValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Form\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\List\Entry\Entry;
use HeimrichHannot\FlareBundle\List\Entry\EntryCollection;
use HeimrichHannot\FlareBundle\List\Entry\EntryLookupInterface;
use HeimrichHannot\FlareBundle\List\Entry\ModelRef;
use HeimrichHannot\FlareBundle\List\FilterFailure;
use HeimrichHannot\FlareBundle\List\ListCompound;
use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;
use HeimrichHannot\FlareBundle\Tests\Engine\EnginePipelineTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * Runs the list view through the real projectors, loaders, query director and an SQLite table.
 */
final class InteractiveViewTest extends TestCase
{
    use EnginePipelineTrait;

    /** Rows 1..3 published, 4 unpublished. */
    private const ROWS = [
        ['id' => 1, 'published' => 1],
        ['id' => 2, 'published' => 1],
        ['id' => 3, 'published' => 1],
        ['id' => 4, 'published' => 0],
    ];

    private int $events = 0;

    public function testEntriesCarryModelRefsAndRunTheEventOnce(): void
    {
        $view = $this->project($this->countingDispatcher());

        $entries = $view->getEntries();
        self::assertSame($entries, $view->getEntries());
        self::assertCount(2, $entries, 'The page.');
        self::assertSame(3, $view->getCount(), 'The total.');
        self::assertTrue($entries->isSealed());
        self::assertEquals(new ModelRef('tl_test', 1), $entries->first()?->meta->modelRef);
        self::assertSame(1, $this->events);
        self::assertSame(2, $this->queries, 'The count and the page, over one director.');
    }

    /**
     * @dataProvider provideEmptyViews
     */
    public function testEmptyViewsDispatchNothing(string $case): void
    {
        $dispatcher = $this->countingDispatcher();

        $view = match ($case) {
            'failed' => $this->project(
                $dispatcher,
                $this->createList(self::publishedOnly())->withFailure(new FilterFailure('tl_flare_filter.3', 'broken')),
            ),
            'invalid' => $this->project(
                $dispatcher,
                $this->createList(self::publishedOnly(), self::idFilter(new RejectingKeywordsControl())),
                ['f' => ['q' => 'bad']],
            ),
            'no rows' => $this->project($dispatcher, rows: [['id' => 1], ['id' => 2]]),
        };

        self::assertSame($case === 'failed', $view->isFailed());
        self::assertSame($case === 'invalid', $view->getForm()->isSubmitted() && !$view->getForm()->isValid());
        self::assertCount(0, $view->getEntries());
        self::assertSame(0, $this->events);
    }

    public static function provideEmptyViews(): iterable
    {
        yield 'failed' => ['failed'];
        yield 'invalid' => ['invalid'];
        yield 'no rows' => ['no rows'];
    }

    public function testLookupInPhaseOneIgnoresFormValues(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ListDriverEntriesEvent::class, static function (ListDriverEntriesEvent $e): void {
            $e->attach('other', static function (EntryCollection $all, EntryLookupInterface $lookup): \Closure {
                $other = $lookup->lookup(1);

                return static fn (Entry $entry): ?Entry => $other;
            });
        });

        $view = $this->project(
            $dispatcher,
            $this->createList(self::publishedOnly(), self::idFilter(new KeywordsFilterControl())),
            ['f' => ['q' => '2']],
        );

        $entries = $view->getEntries();
        self::assertSame([2], self::ids($entries));
        self::assertSame(1, (int) $entries->first()?->other->row['id']);
    }

    private function countingDispatcher(): EventDispatcher
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ListDriverEntriesEvent::class, function (): void {
            ++$this->events;
        });

        return $dispatcher;
    }

    /**
     * Projects $list (default: published rows only) for two items per page and the form `f`.
     *
     * @param array<string, mixed> $query
     * @param list<array<string, mixed>> $rows
     */
    private function project(
        EventDispatcher $dispatcher,
        ?ListCompound   $list = null,
        array           $query = [],
        array           $rows = self::ROWS,
    ): InteractiveView {
        $list ??= $this->createList(self::publishedOnly());
        $intent = new InteractiveIntent(new PaginatorConfig(itemsPerPage: 2), formName: 'f');

        $view = $this->createProjectorRegistry($this->createConnection($rows), $dispatcher, $query)
            ->getProjectorFor($list, $intent)
            ->project($list, $intent);

        self::assertInstanceOf(InteractiveView::class, $view);

        return $view;
    }

    /**
     * The filter `q`: its control decodes the submission, its element restricts to `id = <keywords>`.
     */
    private static function idFilter(FilterControlInterface $control): FilterCompound
    {
        return new FilterCompound(
            element: new ElementBinding(
                new IdEqualsKeywordsElement(),
                'test_element',
                new ComponentConfig([]),
                KeywordsValue::class,
            ),
            alias: 'q',
            control: new ControlBinding(
                $control,
                $control::class,
                new ComponentConfig(['label' => null, 'placeholder' => null]),
            ),
        );
    }

    /**
     * @return list<int> the row ids of the entries
     */
    private static function ids(EntryCollection $entries): array
    {
        return \array_map(static fn (Entry $e): int => (int) $e->row['id'], \iterator_to_array($entries));
    }
}

/**
 * Restricts to `id = <keywords>` for a value; restricts nothing without one.
 */
final class IdEqualsKeywordsElement implements FilterElementInterface
{
    public function buildEffect(
        FilterEffectBuilderInterface $builder,
        ElementContext               $context,
        ?ValueInterface              $value,
    ): void {
        if ($value instanceof KeywordsValue) {
            $builder->addPredicate(SimpleEquationPredicate::class, [
                'operand_left' => 'id',
                'operator' => SqlEquationOperator::EQUALS,
                'operand_right' => (int) $value->keywords,
            ]);
        }
    }
}

/**
 * A text field whose every submission is rejected.
 */
final class RejectingKeywordsControl implements FilterControlInterface
{
    public function buildForm(FilterFormBuilderInterface $builder, ControlContext $context): void
    {
        $builder->single(TextType::class, ['required' => false]);
    }

    public function decode(mixed $data, ControlContext $context): ?KeywordsValue
    {
        if ($data !== null && $data !== '') {
            throw new FilterException('rejected');
        }

        return null;
    }

    public function hydrate(?ValueInterface $value, ControlContext $context): mixed
    {
        return null;
    }
}
