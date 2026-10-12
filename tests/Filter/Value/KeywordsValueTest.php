<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\Value;

use HeimrichHannot\FlareBundle\Filter\Value\KeywordsValue;
use PHPUnit\Framework\TestCase;

final class KeywordsValueTest extends TestCase
{
    /**
     * `SearchKeywordsFilterType` splits on `/\s+OR\s+/i` and then re-tokenises each group, so
     * whitespace variants are multiple representations of one query — which §9 requires the
     * constructor to normalise.
     */
    public function testWhitespaceIsTrimmedAndCollapsed(): void
    {
        self::assertSame('foo OR bar', (new KeywordsValue("  foo \n  OR\t bar  "))->keywords);
    }

    /** Case is folded by the filter type itself; folding here would discard the user's input. */
    public function testCaseIsPreserved(): void
    {
        self::assertSame('FooBar', (new KeywordsValue('FooBar'))->keywords);
    }

    public function testTryFromReturnsNullForBlankOrNonStringInput(): void
    {
        self::assertNull(KeywordsValue::tryFrom(null));
        self::assertNull(KeywordsValue::tryFrom(''));
        self::assertNull(KeywordsValue::tryFrom("   \t\n  "));
        self::assertNull(KeywordsValue::tryFrom(['foo']));
        self::assertNull(KeywordsValue::tryFrom(42));
    }

    public function testTryFromAcceptsStringable(): void
    {
        $stringable = new class implements \Stringable {
            public function __toString(): string
            {
                return ' foo  bar ';
            }
        };

        self::assertSame('foo bar', KeywordsValue::tryFrom($stringable)?->keywords);
    }
}
