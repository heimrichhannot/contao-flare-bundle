<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\DependencyInjection\Factory;

use HeimrichHannot\FlareBundle\DependencyInjection\Factory\TypeNameFactory;
use PHPUnit\Framework\TestCase;

/**
 * `createType()` only calls `basename()` on a string, so the class names below need not exist.
 */
final class TypeNameFactoryTest extends TestCase
{
    /**
     * @dataProvider provideFilterControlClassNames
     */
    public function testCreateFilterControlType(string $className, string $expected): void
    {
        self::assertSame($expected, TypeNameFactory::createFilterControlType($className));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideFilterControlClassNames(): iterable
    {
        $ns = 'HeimrichHannot\\FlareBundle\\Filter\\Control\\';

        yield 'choice' => [$ns . 'ChoiceFilterControl', 'choice'];
        yield 'checkbox' => [$ns . 'CheckboxFilterControl', 'checkbox'];
        yield 'choice bool snake-cases' => [$ns . 'ChoiceBoolFilterControl', 'choice_bool'];
        yield 'date range snake-cases' => [$ns . 'DateRangeFilterControl', 'date_range'];
        yield 'keywords' => [$ns . 'KeywordsFilterControl', 'keywords'];
        yield 'bare Control suffix' => [$ns . 'ChoiceControl', 'choice'];
        yield 'Controller strips first' => [$ns . 'ChoiceControlController', 'choice'];
        yield 'no suffix at all' => [$ns . 'Choice', 'choice'];
        yield 'degenerate empty name' => [$ns . 'FilterControl', ''];
    }

    /**
     * The suffix order is load-bearing: `Str::trimSubstrings()` strips each entry at most once, in
     * order, so `'FilterControl'` must be tried before `'Control'`. With the order reversed,
     * `ChoiceFilterControl` would reduce to `choice_filter`.
     */
    public function testFilterControlSuffixOrderDoesNotLeaveTheFilterWordBehind(): void
    {
        $name = TypeNameFactory::createFilterControlType('Acme\\ChoiceFilterControl');

        self::assertSame('choice', $name);
        self::assertNotSame('choice_filter', $name);
    }

    /**
     * A class named exactly `FilterControl` reduces to the empty string, because `trimSubstrings()`
     * only early-returns on empty *input*. The empty string is the intrinsic sentinel in
     * `tl_flare_filter.control`, so `RegisterFilterControlsPass` rejects it rather than publishing
     * a registry key that means "no control". Pinned here so nobody "fixes" the factory without
     * noticing what depends on this.
     */
    public function testDegenerateNameIsEmptyAndMustBeGuardedByTheCaller(): void
    {
        self::assertSame('', TypeNameFactory::createFilterControlType('Acme\\FilterControl'));
    }

    /**
     * Control and element names may coincide. Only the separate registries and the distinct
     * `flare.filter_control.` / `flare.filter_element.` alias prefixes keep them apart.
     */
    public function testControlAndElementNamesMayCoincide(): void
    {
        self::assertSame(
            TypeNameFactory::createFilterElementType('Acme\\ChoiceFilterElement'),
            TypeNameFactory::createFilterControlType('Acme\\ChoiceFilterControl'),
        );
    }
}
