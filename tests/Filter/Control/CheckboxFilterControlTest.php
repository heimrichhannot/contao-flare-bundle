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
    use BuildsControlContextsTrait;

    public function testUntouchedCheckboxContributesNothingEvenUnderTrueFalse(): void
    {
        $ctl = new CheckboxFilterControl();

        self::assertNull($ctl->decode(null, $this->context($ctl, ['binary_choices' => BoolBinaryChoices::TRUE_FALSE])));
    }

    public function testCheckedMeansTrue(): void
    {
        $ctl = new CheckboxFilterControl();

        self::assertTrue($ctl->decode(true, $this->context($ctl))->state);
    }

    public function testUncheckedMeansFalseOnlyUnderTrueFalse(): void
    {
        $ctl = new CheckboxFilterControl();

        $trueFalse = $this->context($ctl, ['binary_choices' => BoolBinaryChoices::TRUE_FALSE]);
        self::assertFalse($ctl->decode(false, $trueFalse)->state);
        self::assertNull($ctl->decode(false, $this->context($ctl, ['binary_choices' => BoolBinaryChoices::NULL_TRUE])));
        self::assertNull($ctl->decode(false, $this->context($ctl)));
    }

    public function testHydrate(): void
    {
        $ctl = new CheckboxFilterControl();
        $context = $this->context($ctl);

        self::assertTrue($ctl->hydrate(new BoolValue(true), $context));
        self::assertFalse($ctl->hydrate(new BoolValue(false), $context));
        self::assertNull($ctl->hydrate(null, $context));
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

        $ctl->buildForm($builder, $this->context($ctl, ['label' => 'Open']));

        self::assertSame(
            ['type' => CheckboxType::class, 'options' => ['label' => 'Open', 'required' => false]],
            $builder->getSingle(),
        );
    }
}
