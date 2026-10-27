<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\DependencyInjection\Compiler;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterControl;
use HeimrichHannot\FlareBundle\DependencyInjection\Compiler\RegisterFilterControlsPass;
use HeimrichHannot\FlareBundle\Filter\Control\AbstractFilterControl;
use HeimrichHannot\FlareBundle\Filter\ControlScope;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Registry\FilterControlRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Calls `process()` directly on an uncompiled `ContainerBuilder` rather than `compile()`, which
 * would drag in the whole builtin pass pipeline.
 */
final class RegisterFilterControlsPassTest extends TestCase
{
    public function testReturnsEarlyWithoutTheRegistryDefinition(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('test.ctl', (new Definition(PassBoolControl::class))->addTag(AsFilterControl::TAG));

        (new RegisterFilterControlsPass())->process($container);

        self::assertNotSame([], $container->getDefinition('test.ctl')->getTag(AsFilterControl::TAG));
    }

    public function testRegistersTypeValueClassAndPublicAlias(): void
    {
        $container = self::container();
        self::control($container, 'test.ctl', PassBoolControl::class, ['type' => 'flare_checkbox']);

        (new RegisterFilterControlsPass())->process($container);

        $calls = $container->getDefinition(FilterControlRegistry::class)->getMethodCalls();

        self::assertCount(1, $calls);
        self::assertSame('add', $calls[0][0]);
        self::assertEquals([new Reference('test.ctl'), 'flare_checkbox', BoolValue::class], $calls[0][1]);

        self::assertTrue($container->hasAlias('flare.filter_control.flare_checkbox'));
        self::assertTrue($container->getAlias('flare.filter_control.flare_checkbox')->isPublic());
        self::assertSame([], $container->getDefinition('test.ctl')->getTag(AsFilterControl::TAG));
    }

    public function testDerivesTheTypeFromTheClass(): void
    {
        $container = self::container();
        self::control($container, 'test.derived', PassDerivedFilterControl::class, []);

        (new RegisterFilterControlsPass())->process($container);

        $calls = $container->getDefinition(FilterControlRegistry::class)->getMethodCalls();

        self::assertSame('pass_derived', $calls[0][1][1]);
        self::assertTrue($container->hasAlias('flare.filter_control.pass_derived'));
    }

    public function testOneServiceMayRegisterSeveralTypes(): void
    {
        $container = self::container();
        $container->setDefinition('test.multi', (new Definition(PassBoolControl::class))
            ->addTag(AsFilterControl::TAG, ['type' => 'a'])
            ->addTag(AsFilterControl::TAG, ['type' => 'b']));

        (new RegisterFilterControlsPass())->process($container);

        $calls = $container->getDefinition(FilterControlRegistry::class)->getMethodCalls();

        self::assertSame(['a', 'b'], \array_map(static fn (array $call): string => $call[1][1], $calls));
    }

    public function testUnnarrowedDecodeReturnTypeFailsTheBuild(): void
    {
        $container = self::container();
        self::control($container, 'test.bad', PassUnnarrowedControl::class, ['type' => 'a']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/ValueInterface/');

        (new RegisterFilterControlsPass())->process($container);
    }

    public function testDuplicateTypeFailsTheBuild(): void
    {
        $container = self::container();
        self::control($container, 'test.one', PassBoolControl::class, ['type' => 'flare_choice']);
        self::control($container, 'test.two', PassDerivedFilterControl::class, ['type' => 'flare_choice']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/already registered by service/');

        (new RegisterFilterControlsPass())->process($container);
    }

    /**
     * @dataProvider provideReservedNames
     *
     * @param class-string $class
     * @param array<string, mixed> $tag
     */
    public function testReservedTypesFailTheBuild(string $class, array $tag, string $messagePattern): void
    {
        $container = self::container();
        self::control($container, 'test.reserved', $class, $tag);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches($messagePattern);

        (new RegisterFilterControlsPass())->process($container);
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, string}>
     */
    public static function provideReservedNames(): iterable
    {
        yield 'explicit default' => [PassBoolControl::class, ['type' => 'default'], '/reserved/'];
        yield 'derived empty type' => [FilterControl::class, [], '/Cannot derive a filter control type/'];
    }

    public function testNonControlServiceFailsTheBuild(): void
    {
        $container = self::container();
        self::control($container, 'test.plain', \stdClass::class, ['type' => 'a']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/does not implement/');

        (new RegisterFilterControlsPass())->process($container);
    }

    private static function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition(FilterControlRegistry::class, new Definition(FilterControlRegistry::class));

        return $container;
    }

    /**
     * @param array<string, mixed> $tag
     */
    private static function control(ContainerBuilder $container, string $id, string $class, array $tag): void
    {
        $container->setDefinition($id, (new Definition($class))->addTag(AsFilterControl::TAG, $tag));
    }
}

abstract class PassControlBase extends AbstractFilterControl
{
    public function configureOptions(OptionsResolver $resolver): void {}

    protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void {}

    public function buildForm(FilterFormBuilderInterface $builder, ControlScope $scope): void {}

    public function hydrate(?ValueInterface $value, ControlScope $scope): mixed
    {
        return null;
    }
}

final class PassBoolControl extends PassControlBase
{
    public function decode(mixed $data, ControlScope $scope): ?BoolValue
    {
        return null;
    }
}

final class PassDerivedFilterControl extends PassControlBase
{
    public function decode(mixed $data, ControlScope $scope): ?BoolValue
    {
        return null;
    }
}

/** Reduces to the empty string, the intrinsic sentinel. */
final class FilterControl extends PassControlBase
{
    public function decode(mixed $data, ControlScope $scope): ?BoolValue
    {
        return null;
    }
}

final class PassUnnarrowedControl extends PassControlBase
{
    public function decode(mixed $data, ControlScope $scope): ?ValueInterface
    {
        return null;
    }
}
