<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\DependencyInjection\Compiler;

use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterForm;
use HeimrichHannot\FlareBundle\DependencyInjection\Compiler\RegisterFilterFormsPass;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Form\FilterFormInterface;
use HeimrichHannot\FlareBundle\Registry\FilterFormRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Form\FormInterface;

/**
 * Calls `process()` directly on an uncompiled `ContainerBuilder` rather than `compile()`, which
 * would drag in the whole builtin pass pipeline. `PriorityTaggedServiceTrait` works fine on an
 * uncompiled builder.
 */
final class RegisterFilterFormsPassTest extends TestCase
{
    public function testRegistersMetadataLocatorAndAlias(): void
    {
        $container = self::container();
        self::form($container, 'test.choice_form', PassChoiceForm::class, [
            'name' => 'flare_choice',
            'value' => PassStubValue::class,
            'requires' => [PassStubCapability::class],
            'default' => true,
        ]);

        (new RegisterFilterFormsPass())->process($container);

        $registry = $container->getDefinition(FilterFormRegistry::class);

        self::assertSame([
            'flare_choice' => [
                'value' => PassStubValue::class,
                'requires' => [PassStubCapability::class],
                'default' => true,
                'service' => 'test.choice_form',
            ],
        ], $registry->getArgument('$forms'));

        $locator = $registry->getArgument('$formLocator');

        self::assertInstanceOf(Definition::class, $locator);
        self::assertSame(ServiceLocator::class, $locator->getClass());
        self::assertArrayHasKey('container.service_locator', $locator->getTags());
        self::assertSame(['flare_choice'], \array_keys($locator->getArgument(0)));
        self::assertSame('test.choice_form', (string) $locator->getArgument(0)['flare_choice']);

        self::assertTrue($container->hasAlias('flare.filter_form.flare_choice'));
        self::assertTrue($container->getAlias('flare.filter_form.flare_choice')->isPublic());
        self::assertSame([], $container->getDefinition('test.choice_form')->getTag(AsFilterForm::TAG));
    }

    public function testDerivesTheNameFromTheClassWhenNotDeclared(): void
    {
        $container = self::container();
        self::form($container, 'test.derived', PassDerivedFilterForm::class, ['value' => PassStubValue::class]);

        (new RegisterFilterFormsPass())->process($container);

        $forms = $container->getDefinition(FilterFormRegistry::class)->getArgument('$forms');

        self::assertSame(['pass_derived'], \array_keys($forms));
    }

    /** The attribute is repeatable, so one class may serve several value classes. */
    public function testOneServiceMayRegisterSeveralForms(): void
    {
        $container = self::container();
        $container->setDefinition('test.multi', (new Definition(PassChoiceForm::class))
            ->addTag(AsFilterForm::TAG, ['name' => 'a', 'value' => PassStubValue::class])
            ->addTag(AsFilterForm::TAG, ['name' => 'b', 'value' => PassOtherValue::class]));

        (new RegisterFilterFormsPass())->process($container);

        $registry = $container->getDefinition(FilterFormRegistry::class);

        self::assertSame(['a', 'b'], \array_keys($registry->getArgument('$forms')));
        self::assertSame(['a', 'b'], \array_keys($registry->getArgument('$formLocator')->getArgument(0)));
        self::assertTrue($container->hasAlias('flare.filter_form.a'));
        self::assertTrue($container->hasAlias('flare.filter_form.b'));
    }

    public function testReturnsEarlyWithoutTheRegistryDefinition(): void
    {
        $container = new ContainerBuilder();
        self::form($container, 'test.choice_form', PassChoiceForm::class, ['name' => 'flare_choice']);

        (new RegisterFilterFormsPass())->process($container);

        // Proof of the early return: the tag is untouched.
        self::assertNotSame([], $container->getDefinition('test.choice_form')->getTag(AsFilterForm::TAG));
    }

    /**
     * The state of the tree before any form exists: twelve elements tagged, none declaring a value
     * class, no forms at all. Every §10 check must be a no-op rather than failing the build.
     */
    public function testEmptyFormSetWithValuelessElementsIsANoOp(): void
    {
        $container = self::container();

        for ($i = 0; $i < 12; ++$i)
        {
            $container->setDefinition('test.element.' . $i, (new Definition(PassCapableElement::class))
                ->addTag(AsFilterElement::TAG, ['type' => 'flare_element_' . $i, 'value' => null]));
        }

        (new RegisterFilterFormsPass())->process($container);

        $registry = $container->getDefinition(FilterFormRegistry::class);

        self::assertSame([], $registry->getArgument('$forms'));
        self::assertSame([], $registry->getArgument('$formLocator')->getArgument(0));
    }

    /** §10 row 3, element side: a declared value class with no form fails the container build. */
    public function testElementValueClassWithoutAnyFormFailsTheBuild(): void
    {
        $container = self::container();
        $container->setDefinition('test.element', (new Definition(PassCapableElement::class))
            ->addTag(AsFilterElement::TAG, ['type' => 'flare_thing', 'value' => PassStubValue::class]));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/no filter form produces that value/');

        (new RegisterFilterFormsPass())->process($container);
    }

    /** §10 row 3: a form exists for the value class, but this element fails its `requires`. */
    public function testElementFailingTheFormsRequiresFailsTheBuild(): void
    {
        $container = self::container();
        self::form($container, 'test.choice_form', PassChoiceForm::class, [
            'name' => 'flare_choice',
            'value' => PassStubValue::class,
            'requires' => [PassStubCapability::class],
        ]);
        $container->setDefinition('test.element', (new Definition(PassPlainElement::class))
            ->addTag(AsFilterElement::TAG, ['type' => 'flare_thing', 'value' => PassStubValue::class]));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/no filter form produces that value/');

        (new RegisterFilterFormsPass())->process($container);
    }

    public function testElementSatisfyingTheRequiresPasses(): void
    {
        $container = self::container();
        self::form($container, 'test.choice_form', PassChoiceForm::class, [
            'name' => 'flare_choice',
            'value' => PassStubValue::class,
            'requires' => [PassStubCapability::class],
        ]);
        $container->setDefinition('test.element', (new Definition(PassCapableElement::class))
            ->addTag(AsFilterElement::TAG, ['type' => 'flare_thing', 'value' => PassStubValue::class]));

        (new RegisterFilterFormsPass())->process($container);

        self::assertSame(
            ['flare_choice'],
            \array_keys($container->getDefinition(FilterFormRegistry::class)->getArgument('$forms')),
        );
    }

    /**
     * @dataProvider provideInvalidDeclarations
     *
     * @param array<string, mixed> $tag
     */
    public function testInvalidDeclarationsFailTheBuild(string $class, array $tag, string $messagePattern): void
    {
        $container = self::container();
        self::form($container, 'test.bad_form', $class, $tag);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches($messagePattern);

        (new RegisterFilterFormsPass())->process($container);
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, string}>
     */
    public static function provideInvalidDeclarations(): iterable
    {
        // §10 row 3, form side. The FQCN must genuinely not exist — interface_exists() autoloads.
        yield 'requires a non-existent interface' => [
            PassChoiceForm::class,
            ['name' => 'a', 'requires' => ['Acme\\NoSuchInterface']],
            '/not an existing interface/',
        ];

        yield 'requires a class rather than an interface' => [
            PassChoiceForm::class,
            ['name' => 'a', 'requires' => [PassChoiceForm::class]],
            '/not an existing interface/',
        ];

        yield 'requires is not an array' => [
            PassChoiceForm::class,
            ['name' => 'a', 'requires' => 'nope'],
            '/must be a list of interface names/',
        ];

        yield 'reserved name' => [
            PassChoiceForm::class,
            ['name' => 'default'],
            '/reserved/',
        ];

        yield 'degenerate derived name' => [
            FilterForm::class,
            [],
            '/Cannot derive a filter form name/',
        ];

        yield 'class does not implement the interface' => [
            PassPlainElement::class,
            ['name' => 'a'],
            '/does not implement/',
        ];
    }

    public function testDuplicateNameAcrossServicesFailsTheBuild(): void
    {
        $container = self::container();
        self::form($container, 'test.one', PassChoiceForm::class, ['name' => 'flare_choice']);
        self::form($container, 'test.two', PassDerivedFilterForm::class, ['name' => 'flare_choice']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/already registered by service/');

        (new RegisterFilterFormsPass())->process($container);
    }

    public function testTwoDefaultsForOneValueClassFailTheBuild(): void
    {
        $container = self::container();
        self::form($container, 'test.one', PassChoiceForm::class, [
            'name' => 'a',
            'value' => PassStubValue::class,
            'default' => true,
        ]);
        self::form($container, 'test.two', PassDerivedFilterForm::class, [
            'name' => 'b',
            'value' => PassStubValue::class,
            'default' => true,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/both declared as the default/');

        (new RegisterFilterFormsPass())->process($container);
    }

    private static function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition(FilterFormRegistry::class, new Definition(FilterFormRegistry::class));

        return $container;
    }

    /**
     * @param array<string, mixed> $tag
     */
    private static function form(ContainerBuilder $container, string $id, string $class, array $tag): void
    {
        $container->setDefinition($id, (new Definition($class))->addTag(AsFilterForm::TAG, $tag));
    }
}

interface PassStubCapability
{
}

final readonly class PassStubValue
{
}

final readonly class PassOtherValue
{
}

final class PassCapableElement implements PassStubCapability
{
}

final class PassPlainElement
{
}

class PassFilterFormBase implements FilterFormInterface
{
    public function buildForm(FilterFormBuilderInterface $builder, FilterContext $context): void
    {
    }

    public function decode(FormInterface $form, FilterContext $context): ?object
    {
        return null;
    }
}

final class PassChoiceForm extends PassFilterFormBase
{
}

final class PassDerivedFilterForm extends PassFilterFormBase
{
}

/** Named exactly `FilterForm`, so TypeNameFactory derives the empty string from it. */
final class FilterForm extends PassFilterFormBase
{
}
