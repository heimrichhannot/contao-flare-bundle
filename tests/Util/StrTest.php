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
}
