<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Contao\DataContainer\Builder;

use HeimrichHannot\FlareBundle\Contao\DataContainer\Builder\DcaFieldBuilder;
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

    public function testRemoveDropsKeysOfTheBaseDefinition(): void
    {
        $definition = ['eval' => ['rgxp' => 'datim', 'datepicker' => true, 'tl_class' => 'w50']];

        (new DcaFieldBuilder())->remove('eval.rgxp', 'eval.datepicker')->applyTo($definition);

        self::assertSame(['tl_class' => 'w50'], $definition['eval']);
    }

    public function testEvalAfterRemoveSetsTheKeyAgain(): void
    {
        $definition = ['eval' => ['rgxp' => 'datim']];

        (new DcaFieldBuilder())->remove('eval.rgxp')->eval(['rgxp' => 'date'])->applyTo($definition);

        self::assertSame('date', $definition['eval']['rgxp']);
    }

    public function testRemovingAMissingPathIsANoOp(): void
    {
        $definition = ['eval' => ['tl_class' => 'w50']];

        (new DcaFieldBuilder())->remove('eval.rgxp', 'wizard', 'foo.bar.baz')->applyTo($definition);

        self::assertSame(['eval' => ['tl_class' => 'w50']], $definition);
    }

    public function testRemoveLeavesTheOwnSettersAlone(): void
    {
        $definition = ['eval' => ['rgxp' => 'datim']];
        $fn = static fn (mixed $value): mixed => $value;

        (new DcaFieldBuilder())
            ->label('A')
            ->options(['x'])
            ->load($fn)
            ->remove('eval.rgxp')
            ->applyTo($definition);

        self::assertSame(['A', ''], $definition['label']);
        self::assertSame(['x'], $definition['options']);
        self::assertCount(1, $definition['load_callback']);
        self::assertArrayNotHasKey('rgxp', $definition['eval']);
    }

    public function testMakeDatimTurnsATextFieldIntoTheDateTimeInput(): void
    {
        $definition = ['eval' => ['tl_class' => 'w50']];

        (new DcaFieldBuilder())->makeDatim()->applyTo($definition);

        self::assertSame('datim', $definition['eval']['rgxp']);
        self::assertTrue($definition['eval']['datepicker']);
        self::assertSame('w50 wizard', $definition['eval']['tl_class']);

        (new DcaFieldBuilder())->makeDatim()->applyTo($definition);

        self::assertSame('w50 wizard', $definition['eval']['tl_class']);

        $bare = [];
        (new DcaFieldBuilder())->makeDatim()->applyTo($bare);

        self::assertSame('wizard', $bare['eval']['tl_class']);
    }

    public function testMakeDatimShowsAStoredExpressionAsItsTimestamp(): void
    {
        $definition = [];

        (new DcaFieldBuilder())->makeDatim()->applyTo($definition);

        self::assertCount(1, $definition['load_callback']);
        $load = $definition['load_callback'][0];

        self::assertSame((string) \strtotime('today'), $load('today'));
        self::assertSame('1600432000', $load('1600432000'));
        self::assertSame('', $load(''));
        self::assertSame('', $load('   '));
        self::assertSame(1600432000, $load(1600432000));
        self::assertNull($load(null));
    }
}
