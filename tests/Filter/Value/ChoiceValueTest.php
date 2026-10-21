<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\Value;

use HeimrichHannot\FlareBundle\Filter\Value\Choice\ChoiceKey;
use HeimrichHannot\FlareBundle\Filter\Value\Choice\ContaoModelChoice;
use HeimrichHannot\FlareBundle\Filter\Value\Choice\ValueChoice;
use HeimrichHannot\FlareBundle\Filter\Value\ChoiceValue;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilder;
use PHPUnit\Framework\TestCase;

final class ChoiceValueTest extends TestCase
{
    public function testDeduplicatesAndSortsByCanonicalKey(): void
    {
        $value = new ChoiceValue([new ValueChoice('b'), new ValueChoice('a'), new ValueChoice('b', 'dup')]);
        self::assertSame(['a', 'b'], \array_map(ChoiceKey::of(...), $value->choices));
    }

    public function testTryFromIgnoresTheEmptySentinelAndYieldsNullWhenNothingRemains(): void
    {
        self::assertNull(ChoiceValue::tryFrom([ChoicesBuilder::EMPTY_CHOICE, null]));
        self::assertNull(ChoiceValue::tryFrom(null));
        self::assertCount(1, ChoiceValue::tryFrom(new ValueChoice('x'))->choices);
    }

    public function testTryFromIgnoresNonChoiceEntries(): void
    {
        $value = ChoiceValue::tryFrom([new ValueChoice('x'), 'plain', 5, new \stdClass()]);
        self::assertCount(1, $value->choices);
    }

    public function testFingerprintIsOrderIndependentAndKeyBased(): void
    {
        $a = new ChoiceValue([ContaoModelChoice::ref('t', 2), ContaoModelChoice::ref('t', 1)]);
        $b = new ChoiceValue([ContaoModelChoice::ref('t', 1), ContaoModelChoice::ref('t', 2)]);
        self::assertSame($a->fingerprint(), $b->fingerprint());
        self::assertSame('["t.1","t.2"]', $a->fingerprint());
    }
}
