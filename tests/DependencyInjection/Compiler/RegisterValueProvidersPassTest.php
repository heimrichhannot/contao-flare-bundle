<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\DependencyInjection\Compiler;

use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaFieldBuilderInterface;
use HeimrichHannot\FlareBundle\DependencyInjection\Compiler\RegisterValueProvidersPass;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Filter\ValueProvider\ValueProviderInterface;
use HeimrichHannot\FlareBundle\Registry\ValueProviderRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class RegisterValueProvidersPassTest extends TestCase
{
    public function testRegistersByReturnType(): void
    {
        $container = self::container();
        self::provider($container, 'test.bool', PassBoolProvider::class);

        (new RegisterValueProvidersPass())->process($container);

        $calls = $container->getDefinition(ValueProviderRegistry::class)->getMethodCalls();

        self::assertCount(1, $calls);
        self::assertSame('add', $calls[0][0]);
        self::assertEquals([new Reference('test.bool'), BoolValue::class], $calls[0][1]);
        self::assertSame([], $container->getDefinition('test.bool')->getTag(ValueProviderInterface::TAG));
    }

    public function testDuplicateValueClassFailsTheBuild(): void
    {
        $container = self::container();
        self::provider($container, 'test.one', PassBoolProvider::class);
        self::provider($container, 'test.two', PassOtherBoolProvider::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/already has the value provider/');

        (new RegisterValueProvidersPass())->process($container);
    }

    public function testUnnarrowedReturnTypeFailsTheBuild(): void
    {
        $container = self::container();
        self::provider($container, 'test.bad', PassUnnarrowedProvider::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/ValueInterface/');

        (new RegisterValueProvidersPass())->process($container);
    }

    public function testNonProviderServiceFailsTheBuild(): void
    {
        $container = self::container();
        self::provider($container, 'test.plain', \stdClass::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/does not implement/');

        (new RegisterValueProvidersPass())->process($container);
    }

    public function testReturnsEarlyWithoutTheRegistryDefinition(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('test.bool', (new Definition(PassBoolProvider::class))
            ->addTag(ValueProviderInterface::TAG));

        (new RegisterValueProvidersPass())->process($container);

        self::assertNotSame([], $container->getDefinition('test.bool')->getTag(ValueProviderInterface::TAG));
    }

    private static function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition(ValueProviderRegistry::class, new Definition(ValueProviderRegistry::class));

        return $container;
    }

    private static function provider(ContainerBuilder $container, string $id, string $class): void
    {
        $container->setDefinition($id, (new Definition($class))->addTag(ValueProviderInterface::TAG));
    }
}

abstract class PassProviderBase implements ValueProviderInterface
{
    public function buildDcaField(DcaFieldBuilderInterface $field, DcaContext $context): void {}
}

class PassBoolProvider extends PassProviderBase
{
    public function provide(mixed $stored, FilterElementInterface $element): ?BoolValue
    {
        return null;
    }
}

final class PassOtherBoolProvider extends PassBoolProvider {}

final class PassUnnarrowedProvider extends PassProviderBase
{
    public function provide(mixed $stored, FilterElementInterface $element): ?ValueInterface
    {
        return null;
    }
}
