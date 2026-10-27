<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\SchemaResolver;
use HeimrichHannot\FlareBundle\Contract\OptionsContract;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\UndefinedConfigKeyError;
use HeimrichHannot\FlareBundle\Filter\Control\AbstractFilterControl;
use HeimrichHannot\FlareBundle\Filter\ControlScope;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\ElementScope;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
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

        self::assertSame('title', $config->get('field'));
        self::assertFalse($config->get('intrinsic'));
    }

    public function testResolvesOptionsThroughControlSchema(): void
    {
        $resolver = new FilterOptionsResolver(new SchemaResolver());

        self::assertSame(['label' => 'default'], $resolver->resolve(new ConfigAwareControl(), [])->all());
    }

    public function testReturnsOptionsVerbatimWithoutOptionsContract(): void
    {
        $resolver = new FilterOptionsResolver(new SchemaResolver());

        $config = ['anything' => 'goes', 'unvalidated' => true];

        self::assertSame($config, $resolver->resolve(new PlainElement(), $config)->all());
    }

    public function testComponentWithoutOptionsContractDefinesItsGivenKeys(): void
    {
        $config = (new FilterOptionsResolver(new SchemaResolver()))->resolve(new PlainElement(), ['a' => 1]);

        self::assertSame(1, $config->get('a'));

        $this->expectException(UndefinedConfigKeyError::class);
        $config->get('b');
    }

    public function testDefinedOptionWithoutDefaultReadsAsNull(): void
    {
        $config = (new FilterOptionsResolver(new SchemaResolver()))->resolve(new UnsetOptionElement(), []);

        self::assertNull($config->get('x'));
        self::assertFalse($config->has('x'));
    }

    public function testErrorMessagesNameAnonymousComponentsReadably(): void
    {
        $element = new class implements FilterElementInterface {
            public function buildContext(
                FilterContextBuilderInterface $builder,
                ElementScope                  $scope,
                ?ValueInterface               $value,
            ): void {}
        };

        $config = (new FilterOptionsResolver(new SchemaResolver()))->resolve($element, ['a' => 1]);

        try
        {
            $config->get('b');
            self::fail('Expected an UndefinedConfigKeyError.');
        }
        catch (UndefinedConfigKeyError $e)
        {
            self::assertStringNotContainsString("\0", $e->getMessage());
            self::assertStringContainsString('FilterElementInterface@anonymous', $e->getMessage());
        }
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
            self::assertStringStartsWith('[FLARE] Invalid config for', $e->getMessage());
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

    public function buildContext(
        FilterContextBuilderInterface $builder,
        ElementScope                  $scope,
        ?ValueInterface               $value,
    ): void {
    }
}

final class UnsetOptionElement implements FilterElementInterface, OptionsContract
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('x');
    }

    public function buildContext(
        FilterContextBuilderInterface $builder,
        ElementScope                  $scope,
        ?ValueInterface               $value,
    ): void {
    }
}

final class PlainElement implements FilterElementInterface
{
    public function buildContext(
        FilterContextBuilderInterface $builder,
        ElementScope                  $scope,
        ?ValueInterface               $value,
    ): void {
    }
}

final class ConfigAwareControl extends AbstractFilterControl
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('label')->default('default')->allowedTypes('string');
    }

    protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void {}

    public function buildForm(FilterFormBuilderInterface $builder, ControlScope $scope): void
    {
    }

    public function decode(ControlScope $scope, mixed $data): ?BoolValue
    {
        return null;
    }

    public function hydrate(ControlScope $scope, ?ValueInterface $value): mixed
    {
        return null;
    }
}
