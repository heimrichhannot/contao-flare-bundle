<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\Value;

use HeimrichHannot\FlareBundle\Filter\Value\ParentRefValue;
use PHPUnit\Framework\TestCase;

final class ParentRefValueTest extends TestCase
{
    /**
     * Tables and ids are both sorted, because the shape feeds `IN()` clauses per table and so
     * carries no meaningful order (§9). Ids are deduplicated and cast, which is what
     * `ArchiveFilterElement::buildFilter()` builds by hand today as `$grouped`.
     */
    public function testParentsAreNormalisedSortedAndDeduplicated(): void
    {
        $value = new ParentRefValue([
            'tl_news_archive' => [5, 3, '3', 5],
            'tl_calendar' => ['7'],
        ]);

        self::assertSame(
            ['tl_calendar' => [7], 'tl_news_archive' => [3, 5]],
            $value->parents,
        );
    }

    public function testNonPositiveAndNonNumericIdsAreDropped(): void
    {
        $value = new ParentRefValue(['tl_a' => [0, -2, '', 'x', null, true, [], 4]]);

        self::assertSame(['tl_a' => [4]], $value->parents);
    }

    public function testTablesWithNoSurvivingIdAreDroppedEntirely(): void
    {
        self::assertSame(['tl_b' => [1]], (new ParentRefValue(['tl_a' => [0], 'tl_b' => [1]]))->parents);
    }

    public function testUnusableTableKeysAndValuesAreDropped(): void
    {
        // A numeric-looking key would have been cast to int by PHP, and no table name looks like
        // that, so an int key is garbage. A non-iterable value cannot carry ids.
        self::assertSame([], (new ParentRefValue([0 => [1], '  ' => [1], 'tl_a' => 5]))->parents);
    }

    /**
     * The §7.4 claim, pinned: one value class spans both ptable modes, with the static main-ptable
     * case being the single-table degenerate form rather than a separate shape.
     */
    public function testStaticPtableModeIsTheSingleTableCase(): void
    {
        self::assertSame(['tl_news_archive' => [1, 2]], (new ParentRefValue(['tl_news_archive' => [2, 1]]))->parents);
    }

    /**
     * "Use the full whitelist" is the absence of the value, not a state of it: today
     * `ArchiveFilterElement::processRuntimeValue()` already treats nothing-submitted, empty-option
     * and no-model-survived identically, so collapsing all three to null loses nothing — while a
     * flag would let the form decide which rows match (§3.4, §4.1).
     */
    public function testTryFromReturnsNullWhenNoParentSurvives(): void
    {
        self::assertNull(ParentRefValue::tryFrom([]));
        self::assertNull(ParentRefValue::tryFrom(['tl_a' => []]));
        self::assertNull(ParentRefValue::tryFrom(['tl_a' => [0, -1]]));
        self::assertSame(['tl_a' => [1]], ParentRefValue::tryFrom(['tl_a' => [1]])?->parents);
    }

    public function testAcceptsAnyIterableOfIds(): void
    {
        $value = new ParentRefValue(['tl_a' => new \ArrayIterator([2, 1])]);

        self::assertSame(['tl_a' => [1, 2]], $value->parents);
    }
}
