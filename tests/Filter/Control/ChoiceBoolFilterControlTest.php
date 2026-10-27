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

        self::assertTrue($ctl->decode($scope, '1')->state);
        self::assertFalse($ctl->decode($scope, '0')->state);
        self::assertNull($ctl->decode($scope, ''));
        self::assertNull($ctl->decode($scope, null));
        self::assertNull($ctl->decode($scope, 'x'));
    }

    public function testHydrateTable(): void
    {
        $ctl = new ChoiceBoolFilterControl();
        $scope = $this->scope($ctl);

        self::assertSame('1', $ctl->hydrate($scope, new BoolValue(true)));
        self::assertSame('0', $ctl->hydrate($scope, new BoolValue(false)));
        self::assertNull($ctl->hydrate($scope, null));
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
