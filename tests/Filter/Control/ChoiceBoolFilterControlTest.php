<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\Control;

use HeimrichHannot\FlareBundle\Filter\Control\ChoiceBoolFilterControl;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

final class ChoiceBoolFilterControlTest extends TestCase
{
    use BuildsControlContextsTrait;

    public function testDecodeTable(): void
    {
        $ctl = new ChoiceBoolFilterControl();
        $context = $this->context($ctl);

        self::assertTrue($ctl->decode('1', $context)->state);
        self::assertFalse($ctl->decode('0', $context)->state);
        self::assertNull($ctl->decode('', $context));
        self::assertNull($ctl->decode(null, $context));
        self::assertNull($ctl->decode('x', $context));
    }

    public function testHydrateTable(): void
    {
        $ctl = new ChoiceBoolFilterControl();
        $context = $this->context($ctl);

        self::assertSame('1', $ctl->hydrate(new BoolValue(true), $context));
        self::assertSame('0', $ctl->hydrate(new BoolValue(false), $context));
        self::assertNull($ctl->hydrate(null, $context));
    }

    public function testBuildsYesNoSelectWithAnyOption(): void
    {
        $ctl = new ChoiceBoolFilterControl();
        $builder = $this->newFormBuilder();

        $ctl->buildForm($builder, $this->context($ctl));

        $single = $builder->getSingle();
        self::assertSame(ChoiceType::class, $single['type']);
        self::assertSame(['1', '0'], \array_values($single['options']['choices']));
        self::assertNotEmpty($single['options']['placeholder']);
        self::assertFalse($single['options']['required']);
    }
}
