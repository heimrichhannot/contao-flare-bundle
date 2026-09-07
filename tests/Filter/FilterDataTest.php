<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter;

use HeimrichHannot\FlareBundle\Filter\FilterData;
use PHPUnit\Framework\TestCase;

final class FilterDataTest extends TestCase
{
    public function testNoneIsEmpty(): void
    {
        $data = FilterData::none();

        self::assertTrue($data->isEmpty());
        self::assertFalse($data->hasSingle());
        self::assertNull($data->getSingleValue());
        self::assertSame([], $data->all());
        self::assertCount(0, $data);
    }

    public function testSingleHoldsItsValue(): void
    {
        $data = FilterData::single('term');

        self::assertFalse($data->isEmpty());
        self::assertTrue($data->hasSingle());
        self::assertSame('term', $data->getSingleValue());
    }

    /**
     * A submitted null must stay distinguishable from a filter that was never submitted —
     * the distinction the former array bag could not express.
     */
    public function testSingleNullCountsAsSupplied(): void
    {
        $data = FilterData::single(null);

        self::assertTrue($data->hasSingle());
        self::assertNull($data->getSingleValue());
        self::assertNull($data->getSingleValue('fallback'));
        self::assertFalse($data->isEmpty());
    }

    public function testGetSingleValueFallsBackWhenNoSingleWasSupplied(): void
    {
        self::assertSame('fallback', FilterData::none()->getSingleValue('fallback'));
        self::assertSame('fallback', FilterData::of(['from' => 'a'])->getSingleValue('fallback'));
    }

    public function testSingleCarriesNoNamedValues(): void
    {
        $data = FilterData::single('term');

        self::assertSame([], $data->all());
        self::assertFalse($data->has('term'));
        self::assertCount(0, $data);
    }

    public function testOfHoldsNamedValues(): void
    {
        $data = FilterData::of(['from' => 'a', 'to' => null]);

        self::assertFalse($data->hasSingle());
        self::assertFalse($data->isEmpty());
        self::assertSame('a', $data->get('from'));
        self::assertSame(['from' => 'a', 'to' => null], $data->all());
        self::assertCount(2, $data);
    }

    public function testHasDistinguishesSubmittedNullFromMissingField(): void
    {
        $data = FilterData::of(['to' => null]);

        self::assertTrue($data->has('to'));
        self::assertFalse($data->has('from'));
        self::assertNull($data->get('to', 'fallback'));
        self::assertSame('fallback', $data->get('from', 'fallback'));
    }

    public function testOfEmptyArrayIsEmpty(): void
    {
        self::assertTrue(FilterData::of([])->isEmpty());
    }

    public function testIteratesNamedValuesOnly(): void
    {
        self::assertSame(
            ['from' => 'a', 'to' => 'b'],
            \iterator_to_array(FilterData::of(['from' => 'a', 'to' => 'b'])),
        );

        self::assertSame([], \iterator_to_array(FilterData::single('term')));
    }

    public function testToArrayKeepsTheSingleSlotSeparateFromNamedValues(): void
    {
        self::assertSame(
            ['hasSingle' => true, 'single' => 'term', 'values' => []],
            FilterData::single('term')->toArray(),
        );

        // A named field called "single" must not be mistaken for the single value.
        self::assertSame(
            ['hasSingle' => false, 'single' => null, 'values' => ['single' => 'named']],
            FilterData::of(['single' => 'named'])->toArray(),
        );

        self::assertNotSame(
            FilterData::single('x')->toArray(),
            FilterData::of(['single' => 'x'])->toArray(),
        );
    }
}
