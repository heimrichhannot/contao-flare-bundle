<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\SchemaResolver;
use HeimrichHannot\FlareBundle\Contract\OptionsContract;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\Control\AbstractFilterControl;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilder;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterScope;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterOptionsResolver;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class FilterOptionsResolverTest extends TestCase
{
    public function testResolvesOptionsThroughElementSchema(): void
    {
        $resolver = new FilterOptionsResolver(new SchemaResolver());

        $config = $resolver->resolve(new ElementConfigAwareElement(), ['field' => 'title']);

        self::assertSame('title', $config['field']);
        self::assertFalse($config['intrinsic']);
    }

    public function testResolvesOptionsThroughControlSchema(): void
    {
        $resolver = new FilterOptionsResolver(new SchemaResolver());

        self::assertSame(['label' => 'default'], $resolver->resolve(new ConfigAwareControl(), []));
    }

    public function testReturnsOptionsVerbatimWithoutOptionsContract(): void
    {
        $resolver = new FilterOptionsResolver(new SchemaResolver());

        $config = ['anything' => 'goes', 'unvalidated' => true];

        self::assertSame($config, $resolver->resolve(new PlainElement(), $config));
    }

    public function testWrapsSchemaViolationsInFilterException(): void
    {
        $resolver = new FilterOptionsResolver(new SchemaResolver());

        try
        {
            $resolver->resolve(new ElementConfigAwareElement(), ['unknown_key' => 1]);
            self::fail('Expected FilterException.');
        }
        catch (FilterException $e)
        {
            self::assertStringContainsString(ElementConfigAwareElement::class, $e->getMessage());
        }

        try
        {
            $resolver->resolve(new ConfigAwareControl(), ['unknown_key' => 1]);
            self::fail('Expected FilterException.');
        }
        catch (FilterException $e)
        {
            self::assertStringContainsString('control', $e->getMessage());
            self::assertStringContainsString(ConfigAwareControl::class, $e->getMessage());
        }
    }
}

final class ElementConfigAwareElement implements FilterElementInterface, OptionsContract
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('intrinsic')->default(false)->allowedTypes('bool');
        $resolver->define('field')->default(null)->allowedTypes('string', 'null');
    }

    public function buildContext(FilterContextBuilder $builder, ?ValueInterface $value): void
    {
    }
}

final class PlainElement implements FilterElementInterface
{
    public function buildContext(FilterContextBuilder $builder, ?ValueInterface $value): void
    {
    }
}

final class ConfigAwareControl extends AbstractFilterControl
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('label')->default('default')->allowedTypes('string');
    }

    protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void {}

    public function buildForm(FilterFormBuilderInterface $builder, FilterScope $scope): void
    {
    }

    public function decode(mixed $data, FilterScope $scope): ?BoolValue
    {
        return null;
    }

    public function hydrate(?ValueInterface $value, FilterScope $scope): mixed
    {
        return null;
    }
}
