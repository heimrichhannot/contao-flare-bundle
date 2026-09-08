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
     * @dataProvider provideFilterFormClassNames
     */
    public function testCreateFilterFormType(string $className, string $expected): void
    {
        self::assertSame($expected, TypeNameFactory::createFilterFormType($className));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideFilterFormClassNames(): iterable
    {
        $ns = 'HeimrichHannot\\FlareBundle\\Filter\\Form\\';

        yield 'choice' => [$ns . 'ChoiceFilterForm', 'choice'];
        yield 'checkbox' => [$ns . 'CheckboxFilterForm', 'checkbox'];
        yield 'choice bool snake-cases' => [$ns . 'ChoiceBoolFilterForm', 'choice_bool'];
        yield 'date range snake-cases' => [$ns . 'DateRangeFilterForm', 'date_range'];
        yield 'keywords' => [$ns . 'KeywordsFilterForm', 'keywords'];
        yield 'bare Form suffix' => [$ns . 'ChoiceForm', 'choice'];
        yield 'Controller strips first' => [$ns . 'ChoiceFormController', 'choice'];
        yield 'no suffix at all' => [$ns . 'Choice', 'choice'];
        yield 'degenerate empty name' => [$ns . 'FilterForm', ''];
    }

    /**
     * The suffix order is load-bearing: `Str::trimSubstrings()` strips each entry at most once, in
     * order, so `'FilterForm'` must be tried before `'Form'`. With the order reversed,
     * `ChoiceFilterForm` would reduce to `choice_filter`.
     */
    public function testFilterFormSuffixOrderDoesNotLeaveTheFilterWordBehind(): void
    {
        $name = TypeNameFactory::createFilterFormType('Acme\\ChoiceFilterForm');

        self::assertSame('choice', $name);
        self::assertNotSame('choice_filter', $name);
    }

    /**
     * A class named exactly `FilterForm` reduces to the empty string, because `trimSubstrings()`
     * only early-returns on empty *input*. The empty string is the intrinsic sentinel in
     * `tl_flare_filter.formVariant`, so `RegisterFilterFormsPass` rejects it rather than publishing
     * a registry key that means "no form". Pinned here so nobody "fixes" the factory without
     * noticing what depends on this.
     */
    public function testDegenerateNameIsEmptyAndMustBeGuardedByTheCaller(): void
    {
        self::assertSame('', TypeNameFactory::createFilterFormType('Acme\\FilterForm'));
    }

    /**
     * Form and element names may coincide. Only the separate registries and the distinct
     * `flare.filter_form.` / `flare.filter_element.` alias prefixes keep them apart.
     */
    public function testFormAndElementNamesMayCoincide(): void
    {
        self::assertSame(
            TypeNameFactory::createFilterElementType('Acme\\ChoiceFilterElement'),
            TypeNameFactory::createFilterFormType('Acme\\ChoiceFilterForm'),
        );
    }
}
