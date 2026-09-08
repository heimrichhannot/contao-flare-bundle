<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\Value;

use HeimrichHannot\FlareBundle\Filter\Value\ChoiceValue;
use PHPUnit\Framework\TestCase;

final class ChoiceValueTest extends TestCase
{
    /**
     * Sorted because all three consuming filter types emit `IN()`, so order is query-irrelevant and
     * §9 therefore requires normalising it away. Reindexed as a list, which is what today's
     * `FieldValueChoiceFilterElement::extractSubmittedData()` does *not* do — its two `array_filter`
     * calls preserve keys, so its documented `list<string>` is really a holed array.
     */
    public function testKeysAreCastSortedDedupedAndReindexed(): void
    {
        self::assertSame(['a', 'b', 'c'], (new ChoiceValue(['c', 'b', 'a', 'b']))->keys);
        self::assertSame(['1', '2'], (new ChoiceValue([2, 1, '2']))->keys);
    }

    /**
     * Dropping the empty string retires `DcaSelectFieldFilterElement`'s `''`-on-miss artifact,
     * which today reaches `DcaSelectFilterType` as a real search value.
     */
    public function testEmptyStringsAndUnrepresentableEntriesAreDropped(): void
    {
        self::assertSame(['a'], (new ChoiceValue(['a', '', null, true, false, [], new \stdClass()]))->keys);
    }

    public function testStringableEntriesAreAccepted(): void
    {
        $stringable = new class implements \Stringable {
            public function __toString(): string
            {
                return 'x';
            }
        };

        self::assertSame(['x'], (new ChoiceValue([$stringable]))->keys);
    }

    public function testTryFromReturnsNullWhenNothingSurvives(): void
    {
        self::assertNull(ChoiceValue::tryFrom([]));
        self::assertNull(ChoiceValue::tryFrom(['', null, []]));
        self::assertSame(['a'], ChoiceValue::tryFrom(['a'])?->keys);
    }

    /**
     * Case is deliberately preserved. Lowercasing is coupled to `FieldValueChoiceFilterType`'s
     * `LOWER(TRIM())` and would break `DcaSelectFilterType`'s case-sensitive lookup of DCA option
     * keys, so it stays in the element that needs it.
     */
    public function testCaseIsPreserved(): void
    {
        self::assertSame(['A', 'a'], (new ChoiceValue(['a', 'A']))->keys);
    }
}
