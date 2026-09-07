<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Query\Executor;

use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Config\SchemaResolver;
use HeimrichHannot\FlareBundle\Engine\Context\AggregationContext;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterContextFactory;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterData;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterOptionsResolver;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\Query\Executor\FilterExecutor;
use HeimrichHannot\FlareBundle\Query\Factory\FilterQueryBuilderFactory;
use HeimrichHannot\FlareBundle\Query\ListQueryConfig;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Registry\FilterTypeRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Covers the precedence of the runtime data handed to buildFilter(): the collected form values
 * win, an absent key falls through to {@see Filter::$data}, and neither yields
 * {@see FilterData::none()}.
 */
final class FilterExecutorTest extends TestCase
{
    private function createExecutor(): FilterExecutor
    {
        return new FilterExecutor(
            eventDispatcher: new EventDispatcher(),
            filterContextFactory: new FilterContextFactory(new FilterOptionsResolver(new SchemaResolver())),
            filterElementRegistry: new FilterElementRegistry(),
            filterQueryBuilderFactory: new FilterQueryBuilderFactory($this->createMock(Connection::class)),
            filterTypeRegistry: new FilterTypeRegistry([]),
        );
    }

    /**
     * @param array<string|int, FilterData> $filterValues
     */
    private function invoke(Filter $filter, array $filterValues): FilterData
    {
        $driver = new class implements ListDriverInterface {
            public function resolveDcTable(string $type, array $config, array $attributes): string
            {
                return (string) ($config['dc'] ?? '');
            }
        };

        $list = new ListSpec(driver: $driver, type: 'test_list', dc: 'tl_test', filters: ['k' => $filter]);

        $this->createExecutor()->invokeFilters(new ListQueryConfig(
            list: $list,
            context: new AggregationContext(),
            filterValues: $filterValues,
        ));

        $element = $filter->element;
        \assert($element instanceof RecordingFilterElement);

        self::assertNotNull($element->received, 'buildFilter() was never invoked');

        return $element->received;
    }

    public function testCollectedFormValuesWinOverProgrammaticData(): void
    {
        $filter = new Filter(
            element: new RecordingFilterElement(),
            type: 'test_element',
            data: FilterData::single('programmatic'),
        );

        $received = $this->invoke($filter, ['k' => FilterData::single('runtime')]);

        self::assertSame('runtime', $received->getSingleValue());
    }

    public function testProgrammaticDataIsUsedWhenNoFormValuesWereCollected(): void
    {
        $filter = new Filter(
            element: new RecordingFilterElement(),
            type: 'test_element',
            data: FilterData::of(['from' => 'a']),
        );

        $received = $this->invoke($filter, []);

        self::assertSame(['from' => 'a'], $received->all());
    }

    public function testEmptyDataIsPassedWhenNeitherSourceExists(): void
    {
        $filter = new Filter(element: new RecordingFilterElement(), type: 'test_element');

        $received = $this->invoke($filter, []);

        self::assertTrue($received->isEmpty());
        self::assertFalse($received->hasSingle());
    }
}

final class RecordingFilterElement implements FilterElementInterface
{
    public ?FilterData $received = null;

    public function buildForm(FilterFormBuilderInterface $builder, FilterContext $context): void {}

    public function buildFilter(FilterBuilderInterface $builder, FilterContext $context, FilterData $data): void
    {
        $this->received = $data;
    }
}
