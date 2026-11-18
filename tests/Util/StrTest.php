<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Util;

use HeimrichHannot\FlareBundle\Util\Str;
use PHPUnit\Framework\TestCase;

class StrTest extends TestCase
{
    public function testMergePalettesAcceptsNullSegments(): void
    {
        self::assertSame('{a},b', Str::mergePalettes(null, '{a},b', null));
    }

    public function testMergePalettesDropsEmptySegmentsAndTrimsSeparators(): void
    {
        self::assertSame('{a},b;{c},d', Str::mergePalettes('{a},b;', '', ';,', '{c},d'));
    }

    public function testDropEmptyLegends(): void
    {
        self::assertSame(
            '{a_legend},x;{d_legend},y',
            Str::dropEmptyLegends('{a_legend},x;{b_legend};{c_legend:hide};{d_legend},y'),
        );
        self::assertSame('title,type;{a_legend},x', Str::dropEmptyLegends('title,type;{a_legend},x'));
    }

    public function testMergePalettesKeepsLegendsWithoutFields(): void
    {
        self::assertSame('{a_legend},x;{b_legend:hide}', Str::mergePalettes('{a_legend},x', '{b_legend:hide}'));
    }
}
