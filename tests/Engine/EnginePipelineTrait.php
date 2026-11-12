<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Engine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use HeimrichHannot\FlareBundle\Config\SchemaResolver;
use HeimrichHannot\FlareBundle\Config\SourceTransformer;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Engine\Factory\EntryCollectorFactory;
use HeimrichHannot\FlareBundle\Engine\Factory\LoaderFactory;
use HeimrichHannot\FlareBundle\Engine\Intent\Factory\AggregationIntentFactory;
use HeimrichHannot\FlareBundle\Engine\Intent\Factory\ValidationIntentFactory;
use HeimrichHannot\FlareBundle\Engine\Projector\AbstractProjector;
use HeimrichHannot\FlareBundle\Engine\Projector\AggregationProjector;
use HeimrichHannot\FlareBundle\Engine\Projector\InteractiveProjector;
use HeimrichHannot\FlareBundle\Engine\Projector\ValidationProjector;
use HeimrichHannot\FlareBundle\Enum\SqlEquationOperator;
use HeimrichHannot\FlareBundle\Event\ModifyListQueryStructEvent;
use HeimrichHannot\FlareBundle\EventListener\QueryStructModifier\ConditionsModifierListener;
use HeimrichHannot\FlareBundle\EventListener\QueryStructModifier\JoinModifierListener;
use HeimrichHannot\FlareBundle\EventListener\QueryStructModifier\OrderModifierListener;
use HeimrichHannot\FlareBundle\EventListener\QueryStructModifier\PageModifierListener;
use HeimrichHannot\FlareBundle\EventListener\QueryStructModifier\SelectModifierListener;
use HeimrichHannot\FlareBundle\Filter\ElementBinding;
use HeimrichHannot\FlareBundle\Filter\Element\SimpleEquationFilterElement;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterCompoundFactory;
use HeimrichHannot\FlareBundle\Filter\FilterCompound;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterOptionsResolver;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterTransformerResolver;
use HeimrichHannot\FlareBundle\Form\Factory\FilterFormBuilderFactory;
use HeimrichHannot\FlareBundle\Form\Factory\FormHarnessFactory;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\ListCompound;
use HeimrichHannot\FlareBundle\Paginator\Factory\PaginatorFactory;
use HeimrichHannot\FlareBundle\Query\Executor\FilterInvoker;
use HeimrichHannot\FlareBundle\Query\Executor\ListQueryDirector;
use HeimrichHannot\FlareBundle\Query\Factory\FilterQueryBuilderFactory;
use HeimrichHannot\FlareBundle\Query\Factory\ListExecutionContextFactory;
use HeimrichHannot\FlareBundle\Query\Factory\QueryBuilderFactory;
use HeimrichHannot\FlareBundle\Reader\Factory\ReaderUrlGeneratorFactory;
use HeimrichHannot\FlareBundle\Registry\FilterControlRegistry;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Registry\ProjectorRegistry;
use HeimrichHannot\FlareBundle\Tests\Filter\BuildsFilterEffectsTrait;
use HeimrichHannot\FlareBundle\Tests\Fixture\SpyTagger;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Runs lists through the real engine pipeline — projectors, loaders, query director, the bundle's query modifiers —
 * against an SQLite table in memory. For {@see \PHPUnit\Framework\TestCase}s.
 */
trait EnginePipelineTrait
{
    use BuildsFilterEffectsTrait;

    /** Number of list queries built (ModifyListQueryStructEvent dispatches). */
    protected int $queries = 0;

    /** @var array<int, ListQueryDirector> keyed by the dispatcher's object id */
    private array $directors = [];

    /**
     * SQLite in memory with the table `tl_test`, filled with $rows (missing columns take their defaults).
     *
     * @param list<array<string, mixed>> $rows
     */
    protected function createConnection(array $rows): Connection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement(
            "CREATE TABLE tl_test (id INTEGER PRIMARY KEY, pid INTEGER DEFAULT 0, alias TEXT DEFAULT '',"
            . " title TEXT DEFAULT '', published INTEGER DEFAULT 0)",
        );

        foreach ($rows as $row) {
            $connection->insert('tl_test', $row);
        }

        return $connection;
    }

    /**
     * SQLite in memory with the reader discovery tables `tl_article` and `tl_content`, filled with the given rows.
     * Defaults: articles published, `start`/`stop` `''`; elements of type `flare_reader`, visible, `tstamp` 1,
     * `ptable` `tl_article`.
     *
     * @param list<array<string, mixed>> $articles
     * @param list<array<string, mixed>> $elements
     */
    protected function createReaderTables(Connection $connection, array $articles, array $elements): void
    {
        $connection->executeStatement(
            "CREATE TABLE tl_article (id INTEGER PRIMARY KEY, pid INTEGER DEFAULT 0, sorting INTEGER DEFAULT 0,"
            . " published INTEGER DEFAULT 1, start DEFAULT '', stop DEFAULT '')",
        );
        $connection->executeStatement(
            "CREATE TABLE tl_content (id INTEGER PRIMARY KEY, pid INTEGER DEFAULT 0, ptable TEXT DEFAULT 'tl_article',"
            . " type TEXT DEFAULT 'flare_reader', sorting INTEGER DEFAULT 0, invisible INTEGER DEFAULT 0,"
            . " start DEFAULT '', stop DEFAULT '', tstamp INTEGER DEFAULT 1)",
        );

        foreach ($articles as $row) {
            $connection->insert('tl_article', $row);
        }

        foreach ($elements as $row) {
            $connection->insert('tl_content', $row);
        }
    }

    /**
     * The real director over $connection, one per dispatcher: the first call registers the bundle's query modifier
     * listeners with the priorities of their {@see AsEventListener} attributes, and the query counter, on
     * $dispatcher; later calls with the same dispatcher return that director. One connection per dispatcher.
     */
    protected function createListQueryDirector(Connection $connection, EventDispatcher $dispatcher): ListQueryDirector
    {
        // The director holds the dispatcher, so its object id cannot be reused while the memo lives.
        $key = \spl_object_id($dispatcher);

        return $this->directors[$key] ??= $this->buildListQueryDirector($connection, $dispatcher);
    }

    private function buildListQueryDirector(Connection $connection, EventDispatcher $dispatcher): ListQueryDirector
    {
        foreach ([
            new SelectModifierListener($connection),
            new ConditionsModifierListener($connection),
            new PageModifierListener(),
            new OrderModifierListener(),
            new JoinModifierListener(),
        ] as $listener) {
            $attribute = (new \ReflectionClass($listener))->getAttributes(AsEventListener::class)[0]->newInstance();
            $dispatcher->addListener(ModifyListQueryStructEvent::class, $listener, $attribute->priority);
        }

        $dispatcher->addListener(ModifyListQueryStructEvent::class, function (): void {
            ++$this->queries;
        });

        return new ListQueryDirector(
            eventDispatcher: $dispatcher,
            filterInvoker: new FilterInvoker(
                $this->createFilterEffectFactory($this->createPipelineElementRegistry()),
                new FilterQueryBuilderFactory($connection),
            ),
            listExecutionContextFactory: new ListExecutionContextFactory($dispatcher),
            queryBuilderFactory: new QueryBuilderFactory($this->createMock(ValidatorInterface::class), $connection),
            logger: new NullLogger(),
        );
    }

    /**
     * A LoaderFactory over the dispatcher's director ({@see createListQueryDirector()}), whose FilterCompoundFactory
     * knows {@see SimpleEquationFilterElement::TYPE}.
     */
    protected function createLoaderFactory(Connection $connection, EventDispatcher $dispatcher): LoaderFactory
    {
        $filterCompoundFactory = new FilterCompoundFactory(
            filterElementRegistry: $this->createPipelineElementRegistry(),
            filterControlRegistry: new FilterControlRegistry(),
            filterOptionsResolver: new FilterOptionsResolver(new SchemaResolver()),
            filterTransformerResolver: new FilterTransformerResolver(new SourceTransformer(), $dispatcher),
        );

        return new LoaderFactory($filterCompoundFactory, $this->createListQueryDirector($connection, $dispatcher));
    }

    /**
     * The Interactive, Aggregation and Validation projectors over the dispatcher's director and a loader factory
     * over it. Their container serves {@see AbstractProjector::getSubscribedServices()}: an EntryCollectorFactory
     * with a {@see SpyTagger}, a NullLogger, `kernel.debug` = $debug and a RequestStack whose current request
     * carries $query as its query parameters (a submission of form `f` is `['f' => ['q' => '2']]`).
     *
     * @param array<string, mixed> $query
     */
    protected function createProjectorRegistry(
        Connection      $connection,
        EventDispatcher $dispatcher,
        array           $query = [],
        bool            $debug = false,
    ): ProjectorRegistry {
        $request = Request::create('/list', 'GET', $query);
        $request->attributes->set('_route', 'test_route');
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $logger = new NullLogger();
        $loaderFactory = $this->createLoaderFactory($connection, $dispatcher);
        $readerUrlGeneratorFactory = new ReaderUrlGeneratorFactory($dispatcher);
        $formFactory = Forms::createFormFactoryBuilder()
            ->addExtension(new CsrfExtension(new CsrfTokenManager()))
            ->addExtension(new HttpFoundationExtension())
            ->getFormFactory();

        $projectors = [
            new InteractiveProjector(
                aggregationIntentFactory: new AggregationIntentFactory(),
                formHarnessFactory: new FormHarnessFactory(
                    $dispatcher,
                    new FilterFormBuilderFactory($dispatcher, $formFactory),
                    $formFactory,
                    $logger,
                    $this->createMock(TranslatorInterface::class),
                ),
                paginatorFactory: new PaginatorFactory($requestStack, $this->createMock(UrlGeneratorInterface::class)),
                validationIntentFactory: new ValidationIntentFactory($this->createMock(ValidatorInterface::class)),
            ),
            new AggregationProjector(),
            new ValidationProjector(),
        ];
        $registry = new ProjectorRegistry($projectors);

        $services = [
            ListQueryDirector::class => $this->createListQueryDirector($connection, $dispatcher),
            LoaderFactory::class => $loaderFactory,
            ProjectorRegistry::class => $registry,
            ReaderUrlGeneratorFactory::class => $readerUrlGeneratorFactory,
            EntryCollectorFactory::class => new EntryCollectorFactory(
                $dispatcher,
                $loaderFactory,
                $readerUrlGeneratorFactory,
                new SpyTagger(),
            ),
            RequestStack::class => $requestStack,
            LoggerInterface::class => $logger,
            ParameterBagInterface::class => new ParameterBag(['kernel.debug' => $debug]),
        ];

        // exactly the subscribed services: a missing one fails here, not in the middle of a projection
        $factories = [];
        foreach (AbstractProjector::getSubscribedServices() as $id) {
            $service = $services[$id] ?? throw new \LogicException(\sprintf('The test container lacks "%s".', $id));
            $factories[$id] = static fn (): object => $service;
        }

        $container = new ServiceLocator($factories);

        foreach ($projectors as $projector) {
            $projector->setContainer($container);
        }

        return $registry;
    }

    /**
     * A list of type 'test_list' on 'tl_test' with the given filters, keyed by their alias.
     */
    protected function createList(FilterCompound ...$filters): ListCompound
    {
        $driver = new class implements ListDriverInterface {
            public function resolveDcTable(string $type, array $config, array $attributes): string
            {
                return 'tl_test';
            }
        };

        $keyed = [];
        foreach ($filters as $filter) {
            $keyed[$filter->alias] = $filter;
        }

        return new ListCompound(driver: $driver, type: 'test_list', dc: 'tl_test', filters: $keyed);
    }

    /**
     * A fixed filter `published = 1`.
     */
    protected static function publishedOnly(): FilterCompound
    {
        $element = new SimpleEquationFilterElement();
        $config = (new FilterOptionsResolver(new SchemaResolver()))->resolve($element, [
            'left' => 'published',
            'operator' => SqlEquationOperator::EQUALS,
            'right' => 1,
        ]);

        return new FilterCompound(
            element: new ElementBinding($element, SimpleEquationFilterElement::TYPE, $config, null),
            alias: 'published',
        );
    }

    /**
     * The element types the pipeline knows, registered with their attribute as the compiler pass does.
     */
    private function createPipelineElementRegistry(): FilterElementRegistry
    {
        $attribute = (new \ReflectionClass(SimpleEquationFilterElement::class))
            ->getAttributes(AsFilterElement::class)[0]
            ->newInstance();

        return (new FilterElementRegistry())
            ->add(new SimpleEquationFilterElement(), $attribute, SimpleEquationFilterElement::TYPE);
    }
}
