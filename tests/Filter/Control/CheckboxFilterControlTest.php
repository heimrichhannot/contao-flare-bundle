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

        self::assertNull($ctl->decode($this->scope($ctl, ['binary_choices' => BoolBinaryChoices::TRUE_FALSE]), null));
    }

    public function testCheckedMeansTrue(): void
    {
        $ctl = new CheckboxFilterControl();

        self::assertTrue($ctl->decode($this->scope($ctl), true)->state);
    }

    public function testUncheckedMeansFalseOnlyUnderTrueFalse(): void
    {
        $ctl = new CheckboxFilterControl();

        $trueFalse = $this->scope($ctl, ['binary_choices' => BoolBinaryChoices::TRUE_FALSE]);
        self::assertFalse($ctl->decode($trueFalse, false)->state);
        self::assertNull($ctl->decode($this->scope($ctl, ['binary_choices' => BoolBinaryChoices::NULL_TRUE]), false));
        self::assertNull($ctl->decode($this->scope($ctl), false));
    }

    public function testHydrate(): void
    {
        $ctl = new CheckboxFilterControl();
        $scope = $this->scope($ctl);

        self::assertTrue($ctl->hydrate($scope, new BoolValue(true)));
        self::assertFalse($ctl->hydrate($scope, new BoolValue(false)));
        self::assertNull($ctl->hydrate($scope, null));
    }

    public function testTransformerBinaryChoices(): void
    {
        $ctl = new CheckboxFilterControl();

        self::assertSame(
            BoolBinaryChoices::TRUE_FALSE,
            $this->transform($ctl, ['ctl_boolBinaryChoices' => 'true_false'])['binary_choices'],
        );
        self::assertSame(
            BoolBinaryChoices::NULL_TRUE,
            $this->transform($ctl, ['ctl_boolBinaryChoices' => ''])['binary_choices'],
        );
    }

    public function testTransformerLabel(): void
    {
        $ctl = new CheckboxFilterControl();

        self::assertSame('Only open', $this->transform($ctl, ['ctl_label' => 'Only open'])['label']);
        self::assertNull($this->transform($ctl, ['ctl_label' => ''])['label']);
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
