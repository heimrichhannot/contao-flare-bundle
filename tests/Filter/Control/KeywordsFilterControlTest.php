<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\Control;

use HeimrichHannot\FlareBundle\Filter\Control\KeywordsFilterControl;
use HeimrichHannot\FlareBundle\Filter\Value\KeywordsValue;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\TextType;

final class KeywordsFilterControlTest extends TestCase
{
    use BuildsControlScopesTrait;

    public function testDecode(): void
    {
        $ctl = new KeywordsFilterControl();
        $scope = $this->scope($ctl);

        self::assertSame('foo bar', $ctl->decode($scope, "  foo \n bar ")->keywords);
        self::assertNull($ctl->decode($scope, '   '));
        self::assertNull($ctl->decode($scope, null));
    }

    public function testHydrate(): void
    {
        $ctl = new KeywordsFilterControl();
        $scope = $this->scope($ctl);

        self::assertSame('foo', $ctl->hydrate($scope, new KeywordsValue('foo')));
        self::assertNull($ctl->hydrate($scope, null));
    }

    public function testBuildFormWithPlaceholder(): void
    {
        $ctl = new KeywordsFilterControl();
        $builder = $this->newFormBuilder();

        $ctl->buildForm($builder, $this->scope($ctl, ['placeholder' => 'Search']));

        self::assertSame(TextType::class, $builder->getSingle()['type']);
        self::assertSame(['placeholder' => 'Search'], $builder->getSingle()['options']['attr']);
        self::assertFalse($builder->getSingle()['options']['label']);
    }

    public function testBuildFormWithoutPlaceholderHasNoAttr(): void
    {
        $ctl = new KeywordsFilterControl();
        $builder = $this->newFormBuilder();

        $ctl->buildForm($builder, $this->scope($ctl));

        self::assertArrayNotHasKey('attr', $builder->getSingle()['options']);
    }

    public function testTransformer(): void
    {
        $config = $this->transform(new KeywordsFilterControl(), ['ctl_label' => 'Q', 'ctl_placeholder' => '']);

        self::assertSame(['label' => 'Q', 'placeholder' => null], $config);
    }
}
