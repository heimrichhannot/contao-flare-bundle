<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\DataContainer\Inspection;

use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use HeimrichHannot\FlareBundle\Config\SchemaResolver;
use HeimrichHannot\FlareBundle\Config\SourceTransformer;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaFieldBuilderInterface;
use HeimrichHannot\FlareBundle\DataContainer\Inspection\FilterInspector;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterFactory;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterOptionsResolver;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterTransformerResolver;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Form\FilterFormInvoker;
use HeimrichHannot\FlareBundle\Filter\ValueProvider\ValueProviderInterface;
use HeimrichHannot\FlareBundle\List\Collector\ListModelFilterCollector;
use HeimrichHannot\FlareBundle\List\Driver\AbstractListDriver;
use HeimrichHannot\FlareBundle\List\Factory\ListSpecBuilderFactory;
use HeimrichHannot\FlareBundle\List\Factory\ListSpecFactory;
use HeimrichHannot\FlareBundle\List\Resolver\ListDriverResolver;
use HeimrichHannot\FlareBundle\List\Resolver\ListOptionsResolver;
use HeimrichHannot\FlareBundle\List\Resolver\ListTransformerResolver;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Query\Executor\FilterInvoker;
use HeimrichHannot\FlareBundle\Query\Factory\FilterQueryBuilderFactory;
use HeimrichHannot\FlareBundle\Registry\FilterControlRegistry;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Registry\ListDriverRegistry;
use HeimrichHannot\FlareBundle\Registry\ValueProviderRegistry;
use HeimrichHannot\FlareBundle\Tests\DataContainer\Inspection\Stub\AbortingElement;
use HeimrichHannot\FlareBundle\Tests\DataContainer\Inspection\Stub\CheckboxStubControl;
use HeimrichHannot\FlareBundle\Tests\DataContainer\Inspection\Stub\ErroringElement;
use HeimrichHannot\FlareBundle\Tests\DataContainer\Inspection\Stub\IneffectiveElement;
use HeimrichHannot\FlareBundle\Tests\DataContainer\Inspection\Stub\OkElement;
use HeimrichHannot\FlareBundle\Tests\DataContainer\Inspection\Stub\RawElement;
use HeimrichHannot\FlareBundle\Tests\DataContainer\Inspection\Stub\ThrowingElement;
use HeimrichHannot\FlareBundle\Tests\DataContainer\Inspection\Stub\UnbuildableControl;
use HeimrichHannot\FlareBundle\Tests\DataContainer\Inspection\Stub\ValueSensitiveElement;
use HeimrichHannot\FlareBundle\Tests\Filter\BuildsFilterContextsTrait;
use HeimrichHannot\FlareBundle\Tests\Fixture\FilterModelFixture;
use HeimrichHannot\FlareBundle\Tests\Fixture\ListModelFixture;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Forms;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A real {@see FilterInspector} over filter rows a test describes, and the backend collaborators around it. For
 * {@see \Contao\TestCase\ContaoTestCase} subclasses.
 *
 * Element types: 'ok', 'throws' (FilterException "broken" in buildContext()), 'aborts', 'raw' (RuntimeException
 * "raw"), 'errors' (\Error "bug"), 'sensitive' (rejects any value) and 'ineffective' (reason "stub.ineffective"
 * without control and value), the last two consuming BoolValue; controls 'flare_checkbox' (a checkbox) and
 * 'unbuildable' (FilterException "cannot build" in buildForm()) for BoolValue; list driver 'test_list' on tl_test.
 */
trait BuildsFilterInspectorTrait
{
    use BuildsFilterContextsTrait;

    private function createFilterInspector(
        ContaoFramework           $framework,
        ?EventDispatcherInterface $dispatcher = null,
        bool                      $debug = false,
        ?LoggerInterface          $logger = null,
    ): FilterInspector {
        $dispatcher ??= new EventDispatcher();

        $connection = $this->createMock(Connection::class);
        $connection->method('quoteIdentifier')->willReturnArgument(0);
        $connection->method('createExpressionBuilder')->willReturn(new ExpressionBuilder($connection));

        return new FilterInspector(
            eventDispatcher: $dispatcher,
            filterFactory: $this->createFilterFactory(),
            filterInvoker: new FilterInvoker(
                $this->createFilterContextFactory(),
                new FilterQueryBuilderFactory($connection),
            ),
            filterFormInvoker: new FilterFormInvoker($dispatcher, Forms::createFormFactory()),
            listSpecBuilderFactory: $this->createListSpecBuilderFactory($dispatcher),
            translator: self::echoTranslator(),
            logger: $logger ?? new NullLogger(),
            framework: $framework,
            debug: $debug,
        );
    }

    private function createFilterElementRegistry(): FilterElementRegistry
    {
        return (new FilterElementRegistry())
            ->add(new OkElement(), new AsFilterElement(type: 'ok'), 'ok')
            ->add(new ThrowingElement(), new AsFilterElement(type: 'throws'), 'throws')
            ->add(new AbortingElement(), new AsFilterElement(type: 'aborts'), 'aborts')
            ->add(new RawElement(), new AsFilterElement(type: 'raw'), 'raw')
            ->add(new ErroringElement(), new AsFilterElement(type: 'errors'), 'errors')
            ->add(
                new ValueSensitiveElement(),
                new AsFilterElement(type: 'sensitive', value: BoolValue::class),
                'sensitive',
            )
            ->add(
                new IneffectiveElement(),
                new AsFilterElement(type: 'ineffective', value: BoolValue::class),
                'ineffective',
            );
    }

    private function createFilterFactory(): FilterFactory
    {
        $controls = (new FilterControlRegistry())
            ->add(new CheckboxStubControl(), 'flare_checkbox', BoolValue::class)
            ->add(new UnbuildableControl(), 'unbuildable', BoolValue::class);

        $providers = (new ValueProviderRegistry())->add(new class implements ValueProviderInterface {
            public function provide(mixed $stored, FilterElementInterface $element): ?ValueInterface
            {
                return $stored ? new BoolValue(true) : null;
            }

            public function buildDcaField(DcaFieldBuilderInterface $field, DcaContext $context): void {}
        }, BoolValue::class);

        return new FilterFactory(
            filterElementRegistry: $this->createFilterElementRegistry(),
            filterControlRegistry: $controls,
            valueProviderRegistry: $providers,
            filterOptionsResolver: new FilterOptionsResolver(new SchemaResolver()),
            filterTransformerResolver: new FilterTransformerResolver(new SourceTransformer(), new EventDispatcher()),
            logger: new NullLogger(),
        );
    }

    private function createListDriverRegistry(): ListDriverRegistry
    {
        $driver = new class extends AbstractListDriver {
            public function resolveDcTable(string $type, array $config, array $attributes): string
            {
                return 'tl_test';
            }
        };

        $registry = new ListDriverRegistry();
        $registry->add($driver, null, 'test_list');

        return $registry;
    }

    private function createListSpecBuilderFactory(EventDispatcherInterface $dispatcher): ListSpecBuilderFactory
    {
        $registry = $this->createListDriverRegistry();
        $driverResolver = new ListDriverResolver($registry);

        return new ListSpecBuilderFactory(
            eventDispatcher: $dispatcher,
            listDriverResolver: $driverResolver,
            filterCollector: new ListModelFilterCollector(
                $dispatcher,
                $this->createFilterFactory(),
                $registry,
                new NullLogger(),
            ),
            specFactory: new ListSpecFactory(
                $registry,
                new ListOptionsResolver(new SchemaResolver()),
                new ListTransformerResolver(new SourceTransformer(), $dispatcher),
                $driverResolver,
            ),
        );
    }

    /**
     * A FilterModel adapter: findBy() returns the list's rows (null for none), findByPk() the row of the given id.
     *
     * @param list<FilterModel> $rows
     * @param array<int, FilterModel> $byId
     */
    private function mockFilterModelAdapter(array $rows, array $byId = []): Adapter
    {
        $adapter = $this->mockAdapter(['findBy', 'findByPk']);
        $adapter->method('findBy')->willReturn($rows ?: null);
        $adapter->method('findByPk')->willReturnCallback(static fn (mixed $id): ?FilterModel => $byId[(int) $id] ?? null);

        return $adapter;
    }

    /**
     * A Message adapter recording each addError() and addNew() as [method, message].
     *
     * @param list<array{string, string}> $recorded
     */
    private function recordMessages(array &$recorded): Adapter
    {
        $adapter = $this->mockAdapter(['addError', 'addNew']);

        foreach (['addError', 'addNew'] as $method)
        {
            $adapter->method($method)->willReturnCallback(static function (string $message) use (&$recorded, $method): void {
                $recorded[] = [$method, $message];
            });
        }

        return $adapter;
    }

    /**
     * @param array<string, mixed> $row Further columns.
     */
    private static function filterModel(
        int    $id,
        string $type,
        string $alias,
        bool   $published = true,
        array  $row = [],
    ): FilterModelFixture {
        return new FilterModelFixture($row + [
            'id' => $id,
            'pid' => 5,
            'type' => $type,
            'alias' => $alias,
            'published' => $published ? '1' : '',
            'control' => '',
            'value' => '',
        ]);
    }

    private static function listModel(int $id): ListModelFixture
    {
        return new ListModelFixture(['id' => $id, 'type' => 'test_list']);
    }

    /**
     * Echoes the key, followed by its parameters if any: "list.info.broken_filters %count%=1".
     */
    private static function echoTranslator(): TranslatorInterface
    {
        return new class implements TranslatorInterface {
            public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                $pairs = \array_map(
                    static fn (string $key, mixed $value): string => "{$key}={$value}",
                    \array_keys($parameters),
                    $parameters,
                );

                return \implode(' ', [$id, ...$pairs]);
            }

            public function getLocale(): string
            {
                return 'en';
            }
        };
    }
}
