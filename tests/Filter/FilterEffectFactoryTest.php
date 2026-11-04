<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter;

use HeimrichHannot\FlareBundle\Config\ComponentConfig;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Engine\Intent\ConfigurationIntent;
use HeimrichHannot\FlareBundle\Exception\AbortFilteringException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\ElementBinding;
use HeimrichHannot\FlareBundle\Filter\ElementContext;
use HeimrichHannot\FlareBundle\Filter\FilterCompound;
use HeimrichHannot\FlareBundle\Filter\FilterEffectBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Predicate\BooleanPredicate;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\ListCompound;
use HeimrichHannot\FlareBundle\Query\TableAliasRegistry;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use PHPUnit\Framework\TestCase;

final class FilterEffectFactoryTest extends TestCase
{
    use BuildsFilterEffectsTrait;

    public function testConfigurationContextHasItsOwnType(): void
    {
        self::assertSame('configuration', ConfigurationIntent::getIntentType());
    }

    public function testHandsTheValueToTheElement(): void
    {
        $element = new SpyFilterElement();
        $value = new BoolValue(true);

        $this->buildFilterEffect($element, value: $value);
        self::assertSame($value, $element->received);
        self::assertSame(1, $element->calls);

        $this->buildFilterEffect($element);
        self::assertNull($element->received);
        self::assertSame(2, $element->calls);
    }

    public function testElementReceivesTheContext(): void
    {
        $element = new SpyFilterElement();
        $context = $this->buildElementContext($element, ['a' => 1]);

        $effect = $this->createFilterEffectFactory()->create($context, null);

        self::assertSame($context, $element->context);
        self::assertCount(1, $effect->formula->propositions);
        self::assertSame(BooleanPredicate::class, $effect->formula->propositions[0]->predicateClass);
    }

    public function testBuildIsNotExposedToElements(): void
    {
        self::assertFalse((new \ReflectionClass(FilterEffectBuilderInterface::class))->hasMethod('build'));
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

        $context = new ElementContext(
            list: new ListCompound(
                driver: $this->createStub(ListDriverInterface::class),
                type: 'test_list',
                dc: 'tl_test',
            ),
            filter: new FilterCompound(
                element: new ElementBinding($element, 'test_element', new ComponentConfig([]), null),
                alias: 'test',
                targetAlias: $alias,
                targetingForced: $forced,
            ),
            intent: new ConfigurationIntent(),
        );

        $effect = $this->createFilterEffectFactory($registry)->create($context, null);

        self::assertSame($expected, $effect->formula->propositions[0]->targetAlias);
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
            public function buildEffect(
                FilterEffectBuilderInterface $builder,
                ElementContext               $context,
                ?ValueInterface              $value,
            ): void
            {
                $builder->addPredicate(BooleanPredicate::class, ['field' => 'x', 'value' => true], 'other');
            }
        };

        $effect = $this->buildFilterEffect($element);

        self::assertSame('other', $effect->formula->propositions[0]->targetAlias);
    }

    public function testAbortPropagates(): void
    {
        $element = new class extends SpyFilterElement {
            public function buildEffect(
                FilterEffectBuilderInterface $builder,
                ElementContext               $context,
                ?ValueInterface              $value,
            ): void
            {
                $builder->abort();
            }
        };

        $this->expectException(AbortFilteringException::class);
        $this->buildFilterEffect($element);
    }

    public function testRejectsInvalidSqlTableName(): void
    {
        $this->expectException(FlareException::class);
        $this->buildFilterEffect(new SpyFilterElement(), dc: 'tl_test; DROP');
    }
}

class SpyFilterElement implements FilterElementInterface
{
    public ?ValueInterface $received = null;
    public ?ElementContext $context = null;
    public int $calls = 0;

    public function buildEffect(
        FilterEffectBuilderInterface $builder,
        ElementContext               $context,
        ?ValueInterface              $value,
    ): void {
        $this->received = $value;
        $this->context = $context;
        ++$this->calls;
        $builder->addPredicate(BooleanPredicate::class, ['field' => 'published', 'value' => true]);
    }
}
