<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Query\Executor;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use HeimrichHannot\FlareBundle\Exception\AbortFilteringException;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\ElementScope;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Predicate\BooleanPredicate;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Query\Executor\FilterInvoker;
use HeimrichHannot\FlareBundle\Query\Factory\FilterQueryBuilderFactory;
use HeimrichHannot\FlareBundle\Tests\Filter\BuildsFilterContextsTrait;
use PHPUnit\Framework\TestCase;

final class FilterInvokerTest extends TestCase
{
    use BuildsFilterContextsTrait;

    public function testBuildsOneConditionsBuilderPerPredicateCall(): void
    {
        $builders = $this->invoker()->invoke($this->scope(static function (FilterContextBuilderInterface $builder): void {
            $builder->addPredicate(BooleanPredicate::class, ['field' => 'published', 'value' => true]);
            $builder->addPredicate(BooleanPredicate::class, ['field' => 'featured', 'value' => true]);
        }), null);

        self::assertCount(2, $builders);
    }

    public function testFilterExceptionIsWrappedWithTheSource(): void
    {
        try
        {
            $this->invoker()->invoke($this->scope(static function (): void {
                throw new FilterException('boom');
            }), null);
            self::fail('The failure must surface.');
        }
        catch (FilterException $e)
        {
            self::assertSame('tl_flare_filter.9', $e->getSource());
            self::assertSame('boom', $e->getPrevious()?->getMessage());
        }
    }

    public function testOtherExceptionsBecomeFilterExceptions(): void
    {
        try
        {
            $this->invoker()->invoke($this->scope(static function (): void {
                throw new \RuntimeException('raw');
            }), null);
            self::fail('The failure must surface.');
        }
        catch (FilterException $e)
        {
            self::assertSame('raw', $e->getMessage());
            self::assertSame('tl_flare_filter.9', $e->getSource());
        }
    }

    public function testAbortPassesThrough(): void
    {
        $this->expectException(AbortFilteringException::class);

        $this->invoker()->invoke($this->scope(static function (FilterContextBuilderInterface $builder): void {
            $builder->abort();
        }), null);
    }

    private function invoker(): FilterInvoker
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('quoteIdentifier')->willReturnArgument(0);
        $connection->method('createExpressionBuilder')->willReturn(new ExpressionBuilder($connection));

        return new FilterInvoker($this->createFilterContextFactory(), new FilterQueryBuilderFactory($connection));
    }

    /**
     * @param \Closure(FilterContextBuilderInterface): void $buildContext
     */
    private function scope(\Closure $buildContext): ElementScope
    {
        $element = new class ($buildContext) implements FilterElementInterface {
            public function __construct(private readonly \Closure $buildContext) {}

            public function buildContext(
                FilterContextBuilderInterface $builder,
                ElementScope                  $scope,
                ?ValueInterface               $value,
            ): void {
                ($this->buildContext)($builder);
            }
        };

        $scope = $this->buildElementScope($element);

        return new ElementScope(
            list: $scope->list,
            filter: new Filter(element: $scope->filter->element, alias: 'test', source: 'tl_flare_filter.9'),
            engineContext: $scope->engineContext,
        );
    }
}
