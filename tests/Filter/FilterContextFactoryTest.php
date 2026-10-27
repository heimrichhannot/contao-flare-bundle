<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter;

use HeimrichHannot\FlareBundle\Config\ComponentConfig;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Engine\Context\ConfigurationContext;
use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Exception\AbortFilteringException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\ElementBinding;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilder;
use HeimrichHannot\FlareBundle\Filter\FilterScope;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\Predicate\BooleanPredicate;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\Query\TableAliasRegistry;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use PHPUnit\Framework\TestCase;

final class FilterContextFactoryTest extends TestCase
{
    use BuildsFilterContextsTrait;

    public function testConfigurationContextHasItsOwnType(): void
    {
        self::assertSame('configuration', ConfigurationContext::getContextType());
    }

    public function testHandsTheValueToTheElement(): void
    {
        $element = new SpyFilterElement();
        $value = new BoolValue(true);

        $this->buildFilterContext($element, value: $value);
        self::assertSame($value, $element->received);
        self::assertSame(1, $element->calls);

        $this->buildFilterContext($element);
        self::assertNull($element->received);
        self::assertSame(2, $element->calls);
    }

    public function testBuildsContextOverTheScope(): void
    {
        $engineContext = new ConfigurationContext();
        $context = $this->buildFilterContext(new SpyFilterElement(), ['a' => 1], engineContext: $engineContext);

        self::assertSame($engineContext, $context->scope->engineContext);
        self::assertSame(['a' => 1], $context->scope->filter->element->config->all());
        self::assertSame('tl_test', $context->scope->list->dc);
        self::assertCount(1, $context->formula->propositions);
        self::assertSame(BooleanPredicate::class, $context->formula->propositions[0]->predicateClass);
    }

    /**
     * @dataProvider targetAliasCases
     */
    public function testResolvesTheDefaultTargetAliasLikeV01(
        bool    $isTargeted,
        bool    $forced,
        ?string $alias,
        string  $expected,
    ): void {
        $element = new SpyFilterElement();
        $registry = new FilterElementRegistry();
        $registry->add($element, new AsFilterElement(type: 'test_element', isTargeted: $isTargeted), 'test_element');

        $scope = new FilterScope(
            list: new ListSpec(driver: $this->createStub(ListDriverInterface::class), type: 'test_list', dc: 'tl_test'),
            filter: new Filter(
                element: new ElementBinding($element, 'test_element', new ComponentConfig([])),
                alias: 'test',
                targetAlias: $alias,
                targetingForced: $forced,
            ),
            engineContext: new ConfigurationContext(),
        );

        $context = $this->createFilterContextFactory($registry)->create($scope, null);

        self::assertSame($expected, $context->formula->propositions[0]->targetAlias);
    }

    public static function targetAliasCases(): iterable
    {
        yield 'not targeted ignores alias' => [false, false, 'join', TableAliasRegistry::ALIAS_MAIN];
        yield 'targeted uses alias' => [true, false, 'join', 'join'];
        yield 'targeted without alias' => [true, false, null, TableAliasRegistry::ALIAS_MAIN];
        yield 'forced' => [false, true, 'join', 'join'];
    }

    public function testExplicitPredicateAliasWins(): void
    {
        $element = new class extends SpyFilterElement {
            public function buildContext(FilterContextBuilder $builder, ?ValueInterface $value): void
            {
                $builder->addPredicate(BooleanPredicate::class, ['field' => 'x', 'value' => true], 'other');
            }
        };

        $context = $this->buildFilterContext($element);

        self::assertSame('other', $context->formula->propositions[0]->targetAlias);
    }

    public function testAbortPropagates(): void
    {
        $element = new class extends SpyFilterElement {
            public function buildContext(FilterContextBuilder $builder, ?ValueInterface $value): void
            {
                $builder->abort();
            }
        };

        $this->expectException(AbortFilteringException::class);
        $this->buildFilterContext($element);
    }

    public function testRejectsInvalidSqlTableName(): void
    {
        $this->expectException(FlareException::class);
        $this->buildFilterContext(new SpyFilterElement(), dc: 'tl_test; DROP');
    }
}

class SpyFilterElement implements FilterElementInterface
{
    public ?ValueInterface $received = null;
    public int $calls = 0;

    public function buildForm(FilterFormBuilderInterface $builder, FilterContext $context): void
    {
    }

    public function buildContext(FilterContextBuilder $builder, ?ValueInterface $value): void
    {
        $this->received = $value;
        ++$this->calls;
        $builder->addPredicate(BooleanPredicate::class, ['field' => 'published', 'value' => true]);
    }
}
