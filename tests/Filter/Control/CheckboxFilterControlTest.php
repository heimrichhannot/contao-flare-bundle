<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\Control;

use HeimrichHannot\FlareBundle\Enum\BoolBinaryChoices;
use HeimrichHannot\FlareBundle\Filter\Control\CheckboxFilterControl;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

final class CheckboxFilterControlTest extends TestCase
{
    use BuildsControlScopesTrait;

    public function testUntouchedCheckboxContributesNothingEvenUnderTrueFalse(): void
    {
        $ctl = new CheckboxFilterControl();

        self::assertNull($ctl->decode(null, $this->scope($ctl, ['binary_choices' => BoolBinaryChoices::TRUE_FALSE])));
    }

    public function testCheckedMeansTrue(): void
    {
        $ctl = new CheckboxFilterControl();

        self::assertTrue($ctl->decode(true, $this->scope($ctl))->state);
    }

    public function testUncheckedMeansFalseOnlyUnderTrueFalse(): void
    {
        $ctl = new CheckboxFilterControl();

        self::assertFalse($ctl->decode(false, $this->scope($ctl, ['binary_choices' => BoolBinaryChoices::TRUE_FALSE]))->state);
        self::assertNull($ctl->decode(false, $this->scope($ctl, ['binary_choices' => BoolBinaryChoices::NULL_TRUE])));
        self::assertNull($ctl->decode(false, $this->scope($ctl)));
    }

    public function testHydrate(): void
    {
        $ctl = new CheckboxFilterControl();
        $scope = $this->scope($ctl);

        self::assertTrue($ctl->hydrate(new BoolValue(true), $scope));
        self::assertFalse($ctl->hydrate(new BoolValue(false), $scope));
        self::assertNull($ctl->hydrate(null, $scope));
    }

    public function testStoredNullFalseReadsAsTrueFalse(): void
    {
        $ctl = new CheckboxFilterControl();

        self::assertSame(
            BoolBinaryChoices::TRUE_FALSE,
            $this->transform($ctl, ['boolBinaryChoices' => 'null_false'])['binary_choices'],
        );
        self::assertSame(
            BoolBinaryChoices::TRUE_FALSE,
            $this->transform($ctl, ['boolBinaryChoices' => 'true_false'])['binary_choices'],
        );
        self::assertSame(
            BoolBinaryChoices::NULL_TRUE,
            $this->transform($ctl, ['boolBinaryChoices' => ''])['binary_choices'],
        );
    }

    public function testTransformerLabel(): void
    {
        $ctl = new CheckboxFilterControl();

        self::assertSame('Only open', $this->transform($ctl, ['label' => 'Only open'])['label']);
        self::assertNull($this->transform($ctl, ['label' => ''])['label']);
    }

    public function testBuildsSingleCheckbox(): void
    {
        $ctl = new CheckboxFilterControl();
        $builder = $this->newFormBuilder();

        $ctl->buildForm($builder, $this->scope($ctl, ['label' => 'Open']));

        self::assertSame(
            ['type' => CheckboxType::class, 'options' => ['label' => 'Open', 'required' => false]],
            $builder->getSingle(),
        );
    }
}
