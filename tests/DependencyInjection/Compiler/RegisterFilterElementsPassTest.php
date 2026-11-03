<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\DependencyInjection\Compiler;

use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\DependencyInjection\Compiler\RegisterFilterElementsPass;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Registry\ValueProviderRegistry;
use HeimrichHannot\FlareBundle\Tests\Filter\ValueProvider\ProviderStubElement;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class RegisterFilterElementsPassTest extends TestCase
{
    public function testElementValueClassWithoutProviderFailsTheBuild(): void
    {
        $container = self::container();
        self::element($container, ['type' => 'a', 'value' => BoolValue::class]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/no value provider/');

        (new RegisterFilterElementsPass())->process($container);
    }

    public function testElementValueClassWithProviderIsRegistered(): void
    {
        $container = self::container();
        $container->getDefinition(ValueProviderRegistry::class)
            ->addMethodCall('add', [new Reference('some.provider'), BoolValue::class]);
        self::element($container, ['type' => 'a', 'value' => BoolValue::class]);

        (new RegisterFilterElementsPass())->process($container);

        self::assertCount(1, $container->getDefinition(FilterElementRegistry::class)->getMethodCalls());
    }

    public function testElementWithoutValueNeedsNoProvider(): void
    {
        $container = self::container();
        self::element($container, ['type' => 'a']);

        (new RegisterFilterElementsPass())->process($container);

        self::assertCount(1, $container->getDefinition(FilterElementRegistry::class)->getMethodCalls());
    }

    private static function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition(FilterElementRegistry::class, new Definition(FilterElementRegistry::class));
        $container->setDefinition(ValueProviderRegistry::class, new Definition(ValueProviderRegistry::class));

        return $container;
    }

    /**
     * @param array<string, mixed> $tag
     */
    private static function element(ContainerBuilder $container, array $tag): void
    {
        $container->setDefinition('test.element', (new Definition(ProviderStubElement::class))
            ->addTag(AsFilterElement::TAG, $tag));
    }
}
