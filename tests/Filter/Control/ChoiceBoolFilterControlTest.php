<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\Control;

use HeimrichHannot\FlareBundle\Filter\Control\ChoiceBoolFilterControl;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

final class ChoiceBoolFilterControlTest extends TestCase
{
    use BuildsControlScopesTrait;

    public function testDecodeTable(): void
    {
        $ctl = new ChoiceBoolFilterControl();
        $scope = $this->scope($ctl);

        self::assertTrue($ctl->decode('1', $scope)->state);
        self::assertFalse($ctl->decode('0', $scope)->state);
        self::assertNull($ctl->decode('', $scope));
        self::assertNull($ctl->decode(null, $scope));
        self::assertNull($ctl->decode('x', $scope));
    }

    public function testHydrateTable(): void
    {
        $ctl = new ChoiceBoolFilterControl();
        $scope = $this->scope($ctl);

        self::assertSame('1', $ctl->hydrate(new BoolValue(true), $scope));
        self::assertSame('0', $ctl->hydrate(new BoolValue(false), $scope));
        self::assertNull($ctl->hydrate(null, $scope));
    }

    public function testBuildsYesNoSelectWithAnyOption(): void
    {
        $ctl = new ChoiceBoolFilterControl();
        $builder = $this->newFormBuilder();

        $ctl->buildForm($builder, $this->scope($ctl));

        $single = $builder->getSingle();
        self::assertSame(ChoiceType::class, $single['type']);
        self::assertSame(['1', '0'], \array_values($single['options']['choices']));
        self::assertNotEmpty($single['options']['placeholder']);
        self::assertFalse($single['options']['required']);
    }
}
