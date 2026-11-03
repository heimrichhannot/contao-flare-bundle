<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\DataContainer\Builder;

use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaFieldBuilder;
use PHPUnit\Framework\TestCase;

final class DcaFieldBuilderTest extends TestCase
{
    public function testLabelReplacesAReferencedDefaultLabelWithoutWritingThrough(): void
    {
        $lang = ['value' => ['Value', 'Generic description']];
        $definition = ['label' => &$lang['value'], 'eval' => ['tl_class' => 'clr']];

        (new DcaFieldBuilder())->label('Date range', 'Specific description')->applyTo($definition);

        self::assertSame(['Date range', 'Specific description'], $definition['label']);
        self::assertSame(['Value', 'Generic description'], $lang['value']);
    }

    public function testFieldWithoutLabelKeepsItsLabel(): void
    {
        $definition = ['label' => ['Value', '']];

        (new DcaFieldBuilder())->eval(['tl_class' => 'w50'])->applyTo($definition);

        self::assertSame(['Value', ''], $definition['label']);
    }
}
