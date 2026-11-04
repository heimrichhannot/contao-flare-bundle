<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Query\Executor;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use HeimrichHannot\FlareBundle\Exception\AbortFilteringException;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\ElementContext;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\FilterEffectBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Predicate\BooleanPredicate;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Query\Executor\FilterInvoker;
use HeimrichHannot\FlareBundle\Query\Factory\FilterQueryBuilderFactory;
use HeimrichHannot\FlareBundle\Tests\Filter\BuildsFilterEffectsTrait;
use PHPUnit\Framework\TestCase;

final class FilterInvokerTest extends TestCase
{
    use BuildsFilterEffectsTrait;

    public function testBuildsOneConditionsBuilderPerPredicateCall(): void
    {
        $context = $this->context(static function (FilterEffectBuilderInterface $builder): void {
            $builder->addPredicate(BooleanPredicate::class, ['field' => 'published', 'value' => true]);
            $builder->addPredicate(BooleanPredicate::class, ['field' => 'featured', 'value' => true]);
        });

        $builders = $this->invoker()->invoke($context, null);

        self::assertCount(2, $builders);
    }

    public function testFilterExceptionIsWrappedWithTheSource(): void
    {
        try
        {
            $this->invoker()->invoke($this->context(static function (): void {
                throw new FilterException('boom');
            }), null);
            self::fail('The failure must surface.');
        }
        catch (FilterException $e)
        {
            self::assertSame('tl_flare_filter.9', $e->getSource());
            self::assertSame('boom', $e->getPrevious()?->getMessage());
            self::assertStringEndsWith('::buildEffect', (string) $e->getMethod());
        }
    }

    public function testOtherExceptionsBecomeFilterExceptions(): void
    {
        try
        {
            $this->invoker()->invoke($this->context(static function (): void {
                throw new \RuntimeException('raw');
            }), null);
            self::fail('The failure must surface.');
        }
        catch (FilterException $e)
        {
            self::assertSame('raw', $e->getMessage());
            self::assertSame('tl_flare_filter.9', $e->getSource());
            self::assertStringEndsWith('::buildEffect', (string) $e->getMethod());
        }
    }

    public function testAbortPassesThrough(): void
    {
        $this->expectException(AbortFilteringException::class);

        $this->invoker()->invoke($this->context(static function (FilterEffectBuilderInterface $builder): void {
            $builder->abort();
        }), null);
    }

    private function invoker(): FilterInvoker
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('quoteIdentifier')->willReturnArgument(0);
        $connection->method('createExpressionBuilder')->willReturn(new ExpressionBuilder($connection));

        return new FilterInvoker($this->createFilterEffectFactory(), new FilterQueryBuilderFactory($connection));
    }

    /**
     * @param \Closure(FilterEffectBuilderInterface): void $buildEffect
     */
    private function context(\Closure $buildEffect): ElementContext
    {
        $element = new class ($buildEffect) implements FilterElementInterface {
            public function __construct(private readonly \Closure $buildEffect) {}

            public function buildEffect(
                FilterEffectBuilderInterface $builder,
                ElementContext               $context,
                ?ValueInterface              $value,
            ): void {
                ($this->buildEffect)($builder);
            }
        };

        $context = $this->buildElementContext($element);

        return new ElementContext(
            list: $context->list,
            filter: new Filter(element: $context->filter->element, alias: 'test', source: 'tl_flare_filter.9'),
            intent: $context->intent,
        );
    }
}
