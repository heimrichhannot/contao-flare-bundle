<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\DataContainer\Builder;

use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilder;
use PHPUnit\Framework\TestCase;

class DcaBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['TL_DCA']['tl_test'] = [
            'palettes' => ['__prefix__' => '{a},p', '__suffix__' => '{z},s'],
            'fields' => [],
        ];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TL_DCA']['tl_test']);
    }

    public function testSegmentsMergeInPrefixElementControlSuffixOrder(): void
    {
        $dca = new DcaBuilder();
        $dca->palette('{e},el');
        $dca->scope('control')->palette('{control_legend},ctl');
        $dca->apply('tl_test', 'x');

        self::assertSame('{a},p;{e},el;{control_legend},ctl;{z},s', $GLOBALS['TL_DCA']['tl_test']['palettes']['x']);
    }

    public function testMissingSegmentsAreDropped(): void
    {
        $dca = new DcaBuilder();
        $dca->scope('control')->palette('{control_legend},ctl');
        $dca->apply('tl_test', 'x');

        self::assertSame('{a},p;{control_legend},ctl;{z},s', $GLOBALS['TL_DCA']['tl_test']['palettes']['x']);

        $dca = new DcaBuilder();
        $dca->apply('tl_test', 'y');

        self::assertSame('{a},p;{z},s', $GLOBALS['TL_DCA']['tl_test']['palettes']['y']);
    }

    public function testControlScopeSharesFieldsWithParent(): void
    {
        $dca = new DcaBuilder();

        self::assertSame($dca->field('f'), $dca->scope('control')->field('f'));
    }

    public function testPrefixCallableReceivesTheCurrentPrefix(): void
    {
        $dca = new DcaBuilder();
        $dca->prefix(static fn (string $prefix): string => $prefix . ',value');
        $dca->apply('tl_test', 'x');

        self::assertSame('{a},p,value;{z},s', $GLOBALS['TL_DCA']['tl_test']['palettes']['x']);
    }

    public function testComposedPrefixKeepsAnEarlierOverride(): void
    {
        $dca = new DcaBuilder();
        $dca->prefix('{b},q');
        $dca->composePrefix(static fn (string $prefix): string => $prefix . ',value');
        $dca->apply('tl_test', 'x');

        self::assertSame('{b},q,value;{z},s', $GLOBALS['TL_DCA']['tl_test']['palettes']['x']);

        $dca = new DcaBuilder();
        $dca->prefix(static fn (string $prefix): string => $prefix . ',r');
        $dca->composePrefix(static fn (string $prefix): string => $prefix . ',value');
        $dca->apply('tl_test', 'y');

        self::assertSame('{a},p,r,value;{z},s', $GLOBALS['TL_DCA']['tl_test']['palettes']['y']);

        $dca = new DcaBuilder();
        $dca->composePrefix(static fn (string $prefix): string => $prefix . ',value');
        $dca->apply('tl_test', 'z');

        self::assertSame('{a},p,value;{z},s', $GLOBALS['TL_DCA']['tl_test']['palettes']['z']);
    }

    public function testSelectorRegistersFieldAndSubpaletteKey(): void
    {
        $GLOBALS['TL_DCA']['tl_test']['palettes']['__selector__'] = ['other'];

        $dca = new DcaBuilder();
        $dca->selector('formatLabel', 'formatLabelCustom', 'custom');
        $dca->selector('formatLabel', 'formatLabelCustom', 'custom');
        $dca->selector('flag', 'flagField');
        $dca->apply('tl_test', 'x');

        self::assertSame(['other', 'formatLabel', 'flag'], $GLOBALS['TL_DCA']['tl_test']['palettes']['__selector__']);
        self::assertSame(
            ['formatLabel_custom' => 'formatLabelCustom', 'flag' => 'flagField'],
            $GLOBALS['TL_DCA']['tl_test']['subpalettes'],
        );
    }

    public function testUnknownScopeIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new DcaBuilder())->scope('nope');
    }
}
