<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Util;

use HeimrichHannot\FlareBundle\Util\DateTimeHelper;
use PHPUnit\Framework\TestCase;

class DateTimeHelperTest extends TestCase
{
    public function testABareTimestampIsReadAsATimestamp(): void
    {
        self::assertSame(1600432000, DateTimeHelper::toDateTimeImmutable('1600432000')?->getTimestamp());
    }

    public function testATimeSpanKeyIsReadAsItsExpression(): void
    {
        $expected = new \DateTimeImmutable('monday this week', DateTimeHelper::getTimeZone());

        self::assertSame($expected->getTimestamp(), DateTimeHelper::toDateTimeImmutable('this_week')?->getTimestamp());
    }

    public function testAnExpressionIsReadThroughStrtotime(): void
    {
        self::assertSame(\strtotime('2026-01-01'), DateTimeHelper::toDateTimeImmutable('2026-01-01')?->getTimestamp());
    }

    public function testUnreadableTextIsNull(): void
    {
        self::assertNull(DateTimeHelper::toDateTimeImmutable('next blursday'));
    }

    public function testTheEpochIsReadable(): void
    {
        self::assertSame(0, DateTimeHelper::toDateTimeImmutable('0')?->getTimestamp());
        self::assertSame(0, DateTimeHelper::toDateTimeImmutable('@0')?->getTimestamp());
    }

    public function testBlankTextIsNull(): void
    {
        self::assertNull(DateTimeHelper::toDateTimeImmutable(''));
        self::assertNull(DateTimeHelper::toDateTimeImmutable('   '));
    }

    public function testSurroundingWhitespaceIsIgnored(): void
    {
        self::assertSame(1600432000, DateTimeHelper::toDateTimeImmutable(' 1600432000 ')?->getTimestamp());
    }

    public function testTheResultIsInContaosTimeZone(): void
    {
        self::assertSame(
            DateTimeHelper::getTimeZone()->getName(),
            DateTimeHelper::toDateTimeImmutable('1600432000')?->getTimezone()->getName()
        );
    }
}
