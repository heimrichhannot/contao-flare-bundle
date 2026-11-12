<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Controller\ContentElement;

use Contao\CoreBundle\Cache\EntityCacheTags;
use Contao\CoreBundle\Exception\InternalServerErrorHttpException;
use Contao\CoreBundle\Routing\ResponseContext\HtmlHeadBag\HtmlHeadBag;
use Contao\CoreBundle\Routing\ResponseContext\ResponseContext;
use Contao\CoreBundle\Routing\ResponseContext\ResponseContextAccessor;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\Twig\Interop\ContextFactory;
use Contao\CoreBundle\Twig\Loader\ContaoFilesystemLoader;
use Contao\Input;
use Contao\System;
use Contao\TestCase\ContaoTestCase;
use HeimrichHannot\FlareBundle\Controller\ContentElement\ReaderController;
use HeimrichHannot\FlareBundle\Event\ReaderPageMetaEvent;
use HeimrichHannot\FlareBundle\Event\ReaderRenderEvent;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\List\FilterFailure;
use HeimrichHannot\FlareBundle\Tests\Engine\EnginePipelineTrait;
use HeimrichHannot\FlareBundle\Tests\Filter\Element\StubsContaoDcaTrait;
use HeimrichHannot\FlareBundle\Tests\Fixture\ContentModelFixture;
use HeimrichHannot\FlareBundle\Tests\Fixture\ListModelFixture;
use HeimrichHannot\FlareBundle\Tests\Fixture\SpyTagger;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * Runs the reader element through Contao's fragment controller (`__invoke()`) over the real resolver and an SQLite
 * table: row 1 (`visible`) published, row 2 (`hidden`) not.
 */
final class ReaderControllerTest extends ContaoTestCase
{
    use EnginePipelineTrait;
    use StubsContaoDcaTrait;

    private const ROWS = [
        ['id' => 1, 'alias' => 'visible', 'published' => 1],
        ['id' => 2, 'alias' => 'hidden', 'published' => 0],
    ];

    /** The template prints what the controller hands it. */
    private const TEMPLATE = '{{ entry.row.alias }}'
        . '|{{ flare_reader.entryByAutoItem(entry.row.alias) is same as(entry) ? "view" : "other view" }}'
        . '|{{ flare.list.type }}'
        . '|{{ content_model.id }}';

    private SpyTagger $tagger;

    private EventDispatcher $dispatcher;

    private HtmlHeadBag $head;

    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stubDca('tl_test', ['fields' => \array_fill_keys(['id', 'alias', 'title', 'published'], [])]);
        Input::setUnusedRouteParameters([]);

        $this->tagger = new SpyTagger();
        $this->dispatcher = new EventDispatcher();
        $this->head = new HtmlHeadBag();
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    protected function tearDown(): void
    {
        Input::setUnusedRouteParameters([]);

        parent::tearDown();
    }

    public function testAnEntryInsideTheListIsRenderedTaggedAndSetsThePageMeta(): void
    {
        $renders = 0;
        $this->dispatcher->addListener(ReaderRenderEvent::class, static function () use (&$renders): void {
            ++$renders;
        });
        $this->dispatcher->addListener(
            ReaderPageMetaEvent::class,
            static fn (ReaderPageMetaEvent $event) => $event->pageMeta->setTitle('Visible entry'),
        );

        $response = $this->invoke('visible', filters: [self::publishedOnly()]);

        self::assertSame('visible|view|test_list|5', $response->getContent());
        self::assertSame(['tl_test.1'], $this->tagger->tagged);
        self::assertSame(1, $renders);
        self::assertSame('Visible entry', $this->head->getTitle());
    }

    public function testTheAutoItemIsMarkedAsUsed(): void
    {
        Input::setUnusedRouteParameters(['auto_item']);

        $this->invoke('visible');

        self::assertSame([], Input::getUnusedRouteParameters());
    }

    /**
     * @dataProvider provideMissingAutoItems
     */
    public function testWithoutAnAutoItemTheReaderIsNotFoundAndBuildsNothing(?string $autoItem): void
    {
        try {
            $this->invoke($autoItem);
            self::fail('A reader without an auto-item must answer 404.');
        } catch (NotFoundHttpException) {
        }

        self::assertSame([0, 0], [$this->builds, $this->queries]);
        self::assertSame([], $this->tagger->tagged);
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function provideMissingAutoItems(): iterable
    {
        yield 'none' => [null];
        yield 'empty' => [''];
    }

    public function testAnEntryOutsideTheListIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        try {
            $this->invoke('hidden', filters: [self::publishedOnly()]);
        } finally {
            self::assertSame([], $this->tagger->tagged);
            self::assertSame('', $this->head->getTitle() ?? '');
        }
    }

    public function testAFailedListIsAServerErrorAndLogged(): void
    {
        $this->logger->expects(self::once())->method('error');

        try {
            $this->invoke('visible', failures: [new FilterFailure('tl_flare_filter.3', 'broken')]);
            self::fail('A failed list must answer 5xx.');
        } catch (InternalServerErrorHttpException $exception) {
            self::assertInstanceOf(FilterException::class, $exception->getPrevious());
        }

        self::assertSame([], $this->tagger->tagged);
    }

    /**
     * @param list<\HeimrichHannot\FlareBundle\Filter\FilterCompound> $filters
     * @param list<FilterFailure> $failures
     */
    private function invoke(?string $autoItem, array $filters = [], array $failures = []): Response
    {
        $request = new Request();

        if ($autoItem !== null) {
            $request->attributes->set('auto_item', $autoItem);
        }

        $requestStack = new RequestStack();
        $requestStack->push($request);

        // Input::get() reads the auto-item from Contao's container.
        $system = System::getContainer();
        \assert($system instanceof Container);
        $system->set('request_stack', $requestStack);

        $context = new ResponseContext();
        $context->add($this->head);
        $responseContextAccessor = new ResponseContextAccessor($requestStack);
        $responseContextAccessor->setResponseContext($context);

        $scopeMatcher = $this->createConfiguredMock(
            ScopeMatcher::class,
            ['isFrontendRequest' => true, 'isBackendRequest' => false],
        );

        $content = new ContentModelFixture(['id' => 5], new ListModelFixture(['id' => 0, 'type' => 'test_list']));

        $controller = new ReaderController(
            entryTagger: $this->tagger,
            eventDispatcher: $this->dispatcher,
            logger: $this->logger,
            readerResolver: $this->createReaderResolver(
                $this->createConnection(self::ROWS),
                $this->dispatcher,
                $autoItem,
                $filters,
                $failures,
                $content,
            ),
            responseContextAccessor: $responseContextAccessor,
            scopeMatcher: $scopeMatcher,
            translator: $this->createMock(TranslatorInterface::class),
        );
        $controller->setFragmentOptions(['template' => 'content_element/flare_reader', 'type' => 'flare_reader']);
        $controller->setContainer($this->fragmentContainer($requestStack, $scopeMatcher));

        return $controller($request, $content, 'main');
    }

    /**
     * The services Contao's fragment controller needs to render a modern template.
     */
    private function fragmentContainer(RequestStack $requestStack, ScopeMatcher $scopeMatcher): Container
    {
        $container = new Container();
        $container->set('request_stack', $requestStack);
        $container->set('contao.routing.scope_matcher', $scopeMatcher);
        $container->set(
            'contao.twig.filesystem_loader',
            $this->createConfiguredMock(ContaoFilesystemLoader::class, ['exists' => true]),
        );
        $container->set('contao.cache.entity_tags', $this->createMock(EntityCacheTags::class));
        $container->set('contao.twig.interop.context_factory', new ContextFactory());
        $container->set('twig', new Environment(
            new ArrayLoader(['@Contao/content_element/flare_reader.html.twig' => self::TEMPLATE]),
            ['strict_variables' => true],
        ));

        return $container;
    }
}
