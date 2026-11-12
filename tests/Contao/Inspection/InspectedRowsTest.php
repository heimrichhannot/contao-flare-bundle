<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Contao\Inspection;

use Contao\TestCase\ContaoTestCase;
use HeimrichHannot\FlareBundle\Contao\Inspection\InspectedRows;
use HeimrichHannot\FlareBundle\Model\FilterModel;

final class InspectedRowsTest extends ContaoTestCase
{
    /**
     * @dataProvider provideSources
     */
    public function testIdOfNamesOnlyARowOfThisList(?string $source, ?int $expected): void
    {
        $rows = new InspectedRows();
        $rows->add($this->mockClassWithProperties(FilterModel::class, ['id' => 42]));

        self::assertSame($expected, $rows->idOf($source));
    }

    /**
     * @return iterable<string, array{?string, ?int}>
     */
    public static function provideSources(): iterable
    {
        yield 'a row of this list' => ['tl_flare_filter.42', 42];
        yield 'a row of another list' => ['tl_flare_filter.43', null];
        yield 'another table' => ['tl_content.42', null];
        yield 'no source' => [null, null];
        yield 'no id' => ['tl_flare_filter.', null];
        yield 'zero' => ['tl_flare_filter.0', null];
        yield 'negative' => ['tl_flare_filter.-42', null];
        yield 'decimal' => ['tl_flare_filter.42.0', null];
        yield 'not a number' => ['tl_flare_filter.x', null];
    }
}
