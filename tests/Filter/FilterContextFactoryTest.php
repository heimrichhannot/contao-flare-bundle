<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter;

use HeimrichHannot\FlareBundle\Config\ComponentConfig;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Engine\Context\ConfigurationContext;
use HeimrichHannot\FlareBundle\Exception\AbortFilteringException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\ElementBinding;
use HeimrichHannot\FlareBundle\Filter\ElementScope;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilderInterface;
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

    public function testElementReceivesTheScope(): void
    {
        $element = new SpyFilterElement();
        $scope = $this->buildElementScope($element, ['a' => 1]);

        $context = $this->createFilterContextFactory()->create($scope, null);

        self::assertSame($scope, $element->scope);
        self::assertCount(1, $context->formula->propositions);
        self::assertSame(BooleanPredicate::class, $context->formula->propositions[0]->predicateClass);
    }

    public function testBuildIsNotExposedToElements(): void
    {
        self::assertFalse((new \ReflectionClass(FilterContextBuilderInterface::class))->hasMethod('build'));
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

        $scope = new ElementScope(
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
            public function buildContext(
                FilterContextBuilderInterface $builder,
                ElementScope                  $scope,
                ?ValueInterface               $value,
            ): void
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
            public function buildContext(
                FilterContextBuilderInterface $builder,
                ElementScope                  $scope,
                ?ValueInterface               $value,
            ): void
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
    public ?ElementScope $scope = null;
    public int $calls = 0;

    public function buildContext(
        FilterContextBuilderInterface $builder,
        ElementScope                  $scope,
        ?ValueInterface               $value,
    ): void {
        $this->received = $value;
        $this->scope = $scope;
        ++$this->calls;
        $builder->addPredicate(BooleanPredicate::class, ['field' => 'published', 'value' => true]);
    }
}
