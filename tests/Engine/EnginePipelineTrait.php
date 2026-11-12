<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Engine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use HeimrichHannot\FlareBundle\Config\SchemaResolver;
use HeimrichHannot\FlareBundle\Config\SourceTransformer;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Engine\Factory\LoaderFactory;
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
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\ListCompound;
use HeimrichHannot\FlareBundle\Query\Executor\FilterInvoker;
use HeimrichHannot\FlareBundle\Query\Executor\ListQueryDirector;
use HeimrichHannot\FlareBundle\Query\Factory\FilterQueryBuilderFactory;
use HeimrichHannot\FlareBundle\Query\Factory\ListExecutionContextFactory;
use HeimrichHannot\FlareBundle\Query\Factory\QueryBuilderFactory;
use HeimrichHannot\FlareBundle\Registry\FilterControlRegistry;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Tests\Filter\BuildsFilterEffectsTrait;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Runs lists through the real engine pipeline — loaders, query director, the bundle's query modifiers — against an
 * SQLite table in memory. For {@see \PHPUnit\Framework\TestCase}s.
 */
trait EnginePipelineTrait
{
    use BuildsFilterEffectsTrait;

    /** Number of list queries built (ModifyListQueryStructEvent dispatches). */
    protected int $queries = 0;

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
     * The real director over $connection. Registers the bundle's query modifier listeners with the priorities of
     * their {@see AsEventListener} attributes, and the query counter, on $dispatcher: call it once per dispatcher.
     */
    protected function createListQueryDirector(Connection $connection, EventDispatcher $dispatcher): ListQueryDirector
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
     * A LoaderFactory over {@see createListQueryDirector()}, whose FilterCompoundFactory knows
     * {@see SimpleEquationFilterElement::TYPE}.
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
