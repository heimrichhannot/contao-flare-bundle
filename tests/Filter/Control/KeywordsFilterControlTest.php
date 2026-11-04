<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\Control;

use HeimrichHannot\FlareBundle\Filter\Control\KeywordsFilterControl;
use HeimrichHannot\FlareBundle\Filter\Value\KeywordsValue;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\TextType;

final class KeywordsFilterControlTest extends TestCase
{
    use BuildsControlContextsTrait;

    public function testDecode(): void
    {
        $ctl = new KeywordsFilterControl();
        $context = $this->context($ctl);

        self::assertSame('foo bar', $ctl->decode("  foo \n bar ", $context)->keywords);
        self::assertNull($ctl->decode('   ', $context));
        self::assertNull($ctl->decode(null, $context));
    }

    public function testHydrate(): void
    {
        $ctl = new KeywordsFilterControl();
        $context = $this->context($ctl);

        self::assertSame('foo', $ctl->hydrate(new KeywordsValue('foo'), $context));
        self::assertNull($ctl->hydrate(null, $context));
    }

    public function testBuildFormWithPlaceholder(): void
    {
        $ctl = new KeywordsFilterControl();
        $builder = $this->newFormBuilder();

        $ctl->buildForm($builder, $this->context($ctl, ['placeholder' => 'Search']));

        self::assertSame(TextType::class, $builder->getSingle()['type']);
        self::assertSame(['placeholder' => 'Search'], $builder->getSingle()['options']['attr']);
        self::assertFalse($builder->getSingle()['options']['label']);
    }

    public function testBuildFormWithoutPlaceholderHasNoAttr(): void
    {
        $ctl = new KeywordsFilterControl();
        $builder = $this->newFormBuilder();

        $ctl->buildForm($builder, $this->context($ctl));

        self::assertArrayNotHasKey('attr', $builder->getSingle()['options']);
    }

    public function testTransformer(): void
    {
        $config = $this->transform(new KeywordsFilterControl(), ['ctl_label' => 'Q', 'ctl_placeholder' => '']);

        self::assertSame(['label' => 'Q', 'placeholder' => null], $config);
    }
}
