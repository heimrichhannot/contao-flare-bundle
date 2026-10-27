<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Query\Executor;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use HeimrichHannot\FlareBundle\Config\ComponentConfig;
use HeimrichHannot\FlareBundle\Engine\Context\AggregationContext;
use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\Context\ValidationContext;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\Control\FilterControlInterface;
use HeimrichHannot\FlareBundle\Filter\ControlBinding;
use HeimrichHannot\FlareBundle\Filter\ControlScope;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\ElementBinding;
use HeimrichHannot\FlareBundle\Filter\ElementScope;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Predicate\BooleanPredicate;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\Query\Executor\ListQueryDirector;
use HeimrichHannot\FlareBundle\Query\Factory\FilterQueryBuilderFactory;
use HeimrichHannot\FlareBundle\Query\Factory\ListExecutionContextFactory;
use HeimrichHannot\FlareBundle\Query\Factory\QueryBuilderFactory;
use HeimrichHannot\FlareBundle\Query\FilterConditionsBuilder;
use HeimrichHannot\FlareBundle\Query\ListQueryConfig;
use HeimrichHannot\FlareBundle\Tests\Filter\BuildsFilterContextsTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Covers which value a filter is invoked with (SPEC_FILTER_FORMS.md §4.2, §8 step 4) and how a
 * failing filter fails by its role (§8.1).
 */
final class ListQueryDirectorTest extends TestCase
{
    use BuildsFilterContextsTrait;

    /** @var list<array{string, string}> */
    private array $logs = [];

    public function testInitialValueIsIgnoredWithoutAFormValueMap(): void
    {
        $spy = new DirectorSpyElement();
        $filter = $this->filter($spy, control: new DirectorStubControl(), value: new BoolValue(true));

        $this->invoke([$filter], new ValidationContext(), filterValues: []);

        self::assertSame(1, $spy->calls);
        self::assertNull($spy->received);
    }

    public function testFormValueMapWinsForControlledFilters(): void
    {
        $spy = new DirectorSpyElement();
        $filter = $this->filter($spy, control: new DirectorStubControl(), value: new BoolValue(true));
        $submitted = new BoolValue(false);

        $this->invoke([$filter], new AggregationContext(), filterValues: ['f' => $submitted]);
        self::assertSame($submitted, $spy->received);

        // An emptied submission clears the filter instead of falling back to the initial value.
        $this->invoke([$filter], new AggregationContext(), filterValues: ['f' => null]);
        self::assertNull($spy->received);
    }

    public function testFixedValueAppliesInValidation(): void
    {
        $spy = new DirectorSpyElement();
        $fixed = new BoolValue(true);
        $filter = $this->filter($spy, value: $fixed);

        $this->invoke([$filter], new ValidationContext(), filterValues: ['f' => new BoolValue(false)]);

        self::assertSame($fixed, $spy->received);
    }

    public function testControlledFilterFailingWithItsValueDropsOnlyTheValue(): void
    {
        $element = new DirectorValueRejectingElement();
        $next = new DirectorSpyElement();
        $filters = [
            $this->filter($element, alias: 'broken', control: new DirectorStubControl()),
            $this->filter($next, alias: 'next', value: new BoolValue(true)),
        ];

        $builders = $this->invoke($filters, new ValidationContext(), ['broken' => new BoolValue(true)]);

        self::assertSame([true, false], $element->calledWithValue, 'Retried once without the value.');
        self::assertCount(1, $builders, 'The config-bound predicate of the retry applies.');
        self::assertSame(1, $next->calls, 'The filters after it still run.');
        self::assertCount(1, $this->logs);
        self::assertSame('warning', $this->logs[0][0]);
        self::assertStringContainsString('value rejected', $this->logs[0][1]);
    }

    public function testControlledFilterFailingWithoutAValueFailsTheList(): void
    {
        $filter = $this->filter(new DirectorFailingElement(), alias: 'broken', control: new DirectorStubControl());

        $this->expectException(FilterException::class);
        $this->expectExceptionMessage('broken element');

        $this->invoke([$filter], new AggregationContext(), ['broken' => new BoolValue(true)]);
    }

    public function testControlledFilterFailingWithANullValueFailsTheList(): void
    {
        $filter = $this->filter(new DirectorFailingElement(), alias: 'broken', control: new DirectorStubControl());

        $this->expectException(FilterException::class);

        $this->invoke([$filter], new AggregationContext());
    }

    public function testErrorsEscapeUnwrapped(): void
    {
        $filter = $this->filter(new DirectorErroringElement(), control: new DirectorStubControl());

        $this->expectException(\TypeError::class);

        $this->invoke([$filter], new AggregationContext(), ['f' => new BoolValue(true)]);
    }

    public function testOtherExceptionsBecomeFilterExceptions(): void
    {
        $filter = $this->filter(new DirectorThrowingElement(), source: 'tl_flare_filter.9');

        try
        {
            $this->invoke([$filter], new AggregationContext());
            self::fail('A failing filter without a control must fail the list.');
        }
        catch (FilterException $e)
        {
            self::assertInstanceOf(\RuntimeException::class, $e->getPrevious());
            self::assertSame('tl_flare_filter.9', $e->getSource());
        }
    }

    public function testFailingFixedFilterFailsTheList(): void
    {
        $filter = $this->filter(new DirectorFailingElement(), alias: 'broken', source: 'tl_flare_filter.7');

        try
        {
            $this->invoke([$filter], new AggregationContext());
            self::fail('A failing filter without a control must fail the list.');
        }
        catch (FilterException $e)
        {
            self::assertStringContainsString('broken element', $e->getMessage());
            self::assertSame('tl_flare_filter.7', $e->getSource());
            self::assertSame([], $this->logs);
        }
    }

    public function testListFailuresFailTheQueryBeforeAnyFilterRuns(): void
    {
        $spy = new DirectorSpyElement();
        $list = $this->list([$this->filter($spy)])->withFailure('tl_flare_filter.3: broken config');

        try
        {
            $this->createDirector()->createQueryBuilder(new ListQueryConfig(
                list: $list,
                context: new AggregationContext(),
                filterValues: [],
            ));
            self::fail('A list with failures must not be queried.');
        }
        catch (FilterException $e)
        {
            self::assertStringContainsString('tl_flare_filter.3: broken config', $e->getMessage());
            self::assertSame(0, $spy->calls);
        }
    }

    public function testAbortedFilterStillYieldsNoQuery(): void
    {
        $filter = $this->filter(new DirectorAbortingElement(), control: new DirectorStubControl());

        $qb = $this->createDirector()->createQueryBuilder(new ListQueryConfig(
            list: $this->list([$filter]),
            context: new AggregationContext(),
            filterValues: [],
        ));

        self::assertNull($qb);
    }

    /**
     * @param list<Filter> $filters
     * @param array<string, ?ValueInterface> $filterValues
     *
     * @return FilterConditionsBuilder[]
     */
    private function invoke(array $filters, ContextInterface $context, array $filterValues = []): array
    {
        return $this->createDirector()->invokeFilters(new ListQueryConfig(
            list: $this->list($filters),
            context: $context,
            filterValues: $filterValues,
        ));
    }

    private function filter(
        FilterElementInterface  $element,
        string                  $alias = 'f',
        ?FilterControlInterface $control = null,
        ?ValueInterface         $value = null,
        ?string                 $source = null,
    ): Filter {
        return new Filter(
            element: new ElementBinding($element, 'test_element', new ComponentConfig([])),
            alias: $alias,
            control: $control !== null ? new ControlBinding($control, $control::class, new ComponentConfig([])) : null,
            value: $value,
            source: $source,
        );
    }

    /**
     * @param list<Filter> $filters
     */
    private function list(array $filters): ListSpec
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

        return new ListSpec(driver: $driver, type: 'test_list', dc: 'tl_test', filters: $keyed);
    }

    private function createDirector(): ListQueryDirector
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('quoteIdentifier')->willReturnArgument(0);
        $connection->method('createExpressionBuilder')->willReturn(new ExpressionBuilder($connection));
        $dispatcher = new EventDispatcher();

        $logs = &$this->logs;
        $logger = new class ($logs) extends AbstractLogger {
            public function __construct(private array &$logs) {}

            public function log($level, $message, array $context = []): void
            {
                $this->logs[] = [(string) $level, (string) $message];
            }
        };

        return new ListQueryDirector(
            eventDispatcher: $dispatcher,
            filterContextFactory: $this->createFilterContextFactory(),
            filterQueryBuilderFactory: new FilterQueryBuilderFactory($connection),
            listExecutionContextFactory: new ListExecutionContextFactory($dispatcher),
            queryBuilderFactory: new QueryBuilderFactory($this->createMock(ValidatorInterface::class), $connection),
            logger: $logger,
        );
    }
}

/**
 * Records the value it is invoked with and contributes no predicate.
 */
class DirectorSpyElement implements FilterElementInterface
{
    public ?ValueInterface $received = null;
    public int $calls = 0;

    public function buildContext(
        FilterContextBuilderInterface $builder,
        ElementScope                  $scope,
        ?ValueInterface               $value,
    ): void {
        $this->received = $value;
        ++$this->calls;
    }
}

class DirectorFailingElement implements FilterElementInterface
{
    public function buildContext(
        FilterContextBuilderInterface $builder,
        ElementScope                  $scope,
        ?ValueInterface               $value,
    ): void {
        throw new FilterException('broken element');
    }
}

/**
 * Rejects any value; without a value it applies its config-bound predicate.
 */
class DirectorValueRejectingElement implements FilterElementInterface
{
    /** @var list<bool> Whether each call received a value. */
    public array $calledWithValue = [];

    public function buildContext(
        FilterContextBuilderInterface $builder,
        ElementScope                  $scope,
        ?ValueInterface               $value,
    ): void {
        $this->calledWithValue[] = $value !== null;

        if ($value !== null) {
            throw new FilterException('value rejected');
        }

        $builder->addPredicate(BooleanPredicate::class, ['field' => 'published', 'value' => true]);
    }
}

class DirectorErroringElement implements FilterElementInterface
{
    public function buildContext(
        FilterContextBuilderInterface $builder,
        ElementScope                  $scope,
        ?ValueInterface               $value,
    ): void {
        throw new \TypeError('programming error');
    }
}

class DirectorThrowingElement implements FilterElementInterface
{
    public function buildContext(
        FilterContextBuilderInterface $builder,
        ElementScope                  $scope,
        ?ValueInterface               $value,
    ): void {
        throw new \RuntimeException('unexpected');
    }
}

class DirectorAbortingElement implements FilterElementInterface
{
    public function buildContext(
        FilterContextBuilderInterface $builder,
        ElementScope                  $scope,
        ?ValueInterface               $value,
    ): void {
        $builder->abort();
    }
}

class DirectorStubControl implements FilterControlInterface
{
    public function buildForm(FilterFormBuilderInterface $builder, ControlScope $scope): void {}

    public function decode(ControlScope $scope, mixed $data): ?BoolValue
    {
        return BoolValue::tryFrom($data);
    }

    public function hydrate(ControlScope $scope, ?ValueInterface $value): mixed
    {
        return $value instanceof BoolValue ? $value->state : null;
    }
}
