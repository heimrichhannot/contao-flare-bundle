<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\Value;

use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use PHPUnit\Framework\TestCase;

final class BoolValueTest extends TestCase
{
    /**
     * The §9 consequence: "no opinion" is the absence of a value, never an instance carrying null,
     * because two representations of one state cannot be normalised away by a constructor.
     */
    public function testNoOpinionIsTheAbsenceOfAValue(): void
    {
        self::assertNull(BoolValue::tryFrom(null));
        self::assertNull(BoolValue::tryFrom(''));
        self::assertNull(BoolValue::tryFrom('  NULL  '));
        self::assertNull(BoolValue::tryFrom('garbage'));
    }

    public function testFingerprint(): void
    {
        self::assertSame('1', BoolValue::tryFrom(true)?->fingerprint());
        self::assertSame('0', BoolValue::tryFrom(false)?->fingerprint());
    }

    public function testTruthyStringsAndNumbersBecomeTrue(): void
    {
        self::assertTrue(BoolValue::tryFrom('1')?->state);
        self::assertTrue(BoolValue::tryFrom('true')?->state);
        self::assertTrue(BoolValue::tryFrom('YES')?->state);
        self::assertTrue(BoolValue::tryFrom(1)?->state);
    }
}
