<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Controller\ContentElement;

use Contao\CoreBundle\Cache\EntityCacheTags;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\Twig\Interop\ContextFactory;
use Contao\CoreBundle\Twig\Loader\ContaoFilesystemLoader;
use Contao\TestCase\ContaoTestCase;
use FOS\HttpCacheBundle\Http\SymfonyResponseTagger;
use HeimrichHannot\FlareBundle\Config\ComponentConfig;
use HeimrichHannot\FlareBundle\Config\SchemaResolver;
use HeimrichHannot\FlareBundle\Controller\ContentElement\ListViewController;
use HeimrichHannot\FlareBundle\Engine\Factory\EngineFactory;
use HeimrichHannot\FlareBundle\Engine\Intent\Factory\InteractiveIntentFactory;
use HeimrichHannot\FlareBundle\Engine\View\InteractiveView;
use HeimrichHannot\FlareBundle\Event\ListDriverBuildListEvent;
use HeimrichHannot\FlareBundle\Event\ListViewRenderEvent;
use HeimrichHannot\FlareBundle\Filter\ControlBinding;
use HeimrichHannot\FlareBundle\Filter\Control\KeywordsFilterControl;
use HeimrichHannot\FlareBundle\Filter\ElementBinding;
use HeimrichHannot\FlareBundle\Filter\Element\SearchKeywordsFilterElement;
use HeimrichHannot\FlareBundle\Filter\FilterCompound;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterOptionsResolver;
use HeimrichHannot\FlareBundle\Filter\Value\KeywordsValue;
use HeimrichHannot\FlareBundle\List\ListCompound;
use HeimrichHannot\FlareBundle\List\Output;
use HeimrichHannot\FlareBundle\Registry\EngineModRegistry;
use HeimrichHannot\FlareBundle\Sort\Factory\SortOrderSequenceFactory;
use HeimrichHannot\FlareBundle\Tests\Engine\EnginePipelineTrait;
use HeimrichHannot\FlareBundle\Tests\Filter\Element\StubsContaoDcaTrait;
use HeimrichHannot\FlareBundle\Tests\Fixture\ContentModelFixture;
use HeimrichHannot\FlareBundle\Tests\Fixture\ListModelFixture;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * Runs the list view element through Contao's fragment controller (`__invoke()`) over the real list builder and
 * engine and an SQLite table.
 */
final class ListViewControllerTest extends ContaoTestCase
{
    use EnginePipelineTrait;
    use StubsContaoDcaTrait;

    private const ROWS = [
        ['id' => 1, 'alias' => 'first', 'title' => 'First', 'published' => 1],
        ['id' => 2, 'alias' => 'second', 'title' => 'Second', 'published' => 1],
    ];

    /** The template creates no view. */
    private const TEMPLATE = '{{ flare.list.type }}';

    private EventDispatcher $dispatcher;

    private ?ListCompound $renderedList = null;

    private ?InteractiveView $renderedView = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stubDca('tl_test', ['fields' => \array_fill_keys(['id', 'alias', 'title', 'published'], [])]);

        $this->dispatcher = new EventDispatcher();
        $this->dispatcher->addListener(ListViewRenderEvent::class, function (ListViewRenderEvent $event): void {
            $this->renderedList = $event->engine->getList();
            $view = $event->engine->createView();
            self::assertInstanceOf(InteractiveView::class, $view);
            $this->renderedView = $view;
        });
    }

    public function testTheListViewRunsOnItsOutputsList(): void
    {
        $response = $this->invoke([
            self::idOtherThan(2, 'a'),
            self::idOtherThan(1, 'b')->withOutputs([Output::ListView]),
            self::keywordsFilter('c')->withOutputs([]),
        ]);

        self::assertSame('test_list', $response->getContent());
        self::assertSame(['a', 'b'], \array_keys($this->renderedList?->filters ?? []));
        self::assertNotNull($this->renderedView);
        self::assertFalse($this->renderedView->getForm()->has('c'), 'A control outside the list is not mounted.');
    }

    /**
     * A search over `title` with a keywords control.
     */
    private static function keywordsFilter(string $alias): FilterCompound
    {
        $element = new SearchKeywordsFilterElement();
        $config = (new FilterOptionsResolver(new SchemaResolver()))->resolve($element, ['columns' => ['title']]);

        return new FilterCompound(
            element: new ElementBinding($element, SearchKeywordsFilterElement::TYPE, $config, KeywordsValue::class),
            alias: $alias,
            control: new ControlBinding(
                new KeywordsFilterControl(),
                KeywordsFilterControl::TYPE,
                new ComponentConfig(['label' => null, 'placeholder' => null]),
            ),
        );
    }

    /**
     * Renders the list view element 5 of a stored list whose builder gets $filters.
     *
     * @param list<FilterCompound> $filters
     */
    private function invoke(array $filters): Response
    {
        $this->dispatcher->addListener(
            ListDriverBuildListEvent::class,
            static function (ListDriverBuildListEvent $event) use ($filters): void {
                foreach ($filters as $filter) {
                    $event->builder->addFilter($filter);
                }
            },
        );

        $connection = $this->createConnection(self::ROWS);
        $request = new Request();
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $scopeMatcher = $this->createConfiguredMock(
            ScopeMatcher::class,
            ['isFrontendRequest' => true, 'isBackendRequest' => false],
        );

        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        $controller = new ListViewController(
            engineFactory: new EngineFactory(
                new EngineModRegistry([]),
                $this->createProjectorRegistry($connection, $this->dispatcher),
            ),
            eventDispatcher: $this->dispatcher,
            interactiveIntentFactory: new InteractiveIntentFactory(new SortOrderSequenceFactory(), $validator),
            kernel: $this->createMock(KernelInterface::class),
            listFactory: $this->createListCompoundBuilderFactory($this->dispatcher),
            logger: $this->createMock(LoggerInterface::class),
            scopeMatcher: $scopeMatcher,
            responseTagger: $this->createMock(SymfonyResponseTagger::class),
            translator: $this->createMock(TranslatorInterface::class),
        );
        $controller->setFragmentOptions(['template' => 'content_element/flare_listview', 'type' => 'flare_listview']);
        $controller->setContainer($this->fragmentContainer($requestStack, $scopeMatcher));

        $content = new ContentModelFixture(['id' => 5], new ListModelFixture(['id' => 0, 'type' => 'test_list']));

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
            new ArrayLoader(['@Contao/content_element/flare_listview.html.twig' => self::TEMPLATE]),
            ['strict_variables' => true],
        ));

        return $container;
    }
}
