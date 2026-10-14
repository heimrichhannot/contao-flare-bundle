<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Registry;

use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Form\FilterFormInterface;
use HeimrichHannot\FlareBundle\Registry\FilterFormRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Form\FormInterface;

final class FilterFormRegistryTest extends TestCase
{
    /**
     * The whole reason the registry keeps metadata as plain arrays and the services behind a
     * locator: the backend options callback and the election run on every DCA load, and a list uses
     * a handful of forms rather than all of them. If this ever regresses, the registry has silently
     * become as eager as the method-call registries it deliberately diverges from.
     */
    public function testMetadataReadsDoNotInstantiateForms(): void
    {
        $built = 0;

        $locator = new ServiceLocator([
            'flare_choice' => static function () use (&$built): FilterFormInterface {
                ++$built;

                return new RegistryFormStub();
            },
        ]);

        $registry = new FilterFormRegistry($locator, [
            'flare_choice' => [
                'value' => RegistryStubValue::class,
                'requires' => [RegistryStubCapability::class],
                'default' => true,
                'service' => 'test.choice_form',
            ],
        ]);

        self::assertSame(['flare_choice'], $registry->keys());
        self::assertSame(RegistryStubValue::class, $registry->getValueClass('flare_choice'));
        self::assertSame([RegistryStubCapability::class], $registry->getRequires('flare_choice'));
        self::assertTrue($registry->isDefault('flare_choice'));
        self::assertSame('test.choice_form', $registry->getServiceId('flare_choice'));
        self::assertSame(['flare_choice'], $registry->findNames(RegistryStubValue::class, RegistryCapableElement::class));
        self::assertSame(0, $built, 'Metadata reads must not construct any form service.');

        self::assertInstanceOf(RegistryFormStub::class, $registry->getService('flare_choice'));
        self::assertSame(1, $built);
    }

    public function testAccessorsTolerateNullAndUnknownNames(): void
    {
        $registry = self::registry();

        self::assertFalse($registry->has('nope'));
        self::assertNull($registry->getService(null));
        self::assertNull($registry->getService('nope'));
        self::assertNull($registry->getValueClass(null));
        self::assertNull($registry->getValueClass('nope'));
        self::assertSame([], $registry->getRequires('nope'));
        self::assertFalse($registry->isDefault(null));
        self::assertNull($registry->getServiceId('nope'));
    }

    public function testElectionRequiresAMatchingValueClass(): void
    {
        $registry = self::registry();

        self::assertSame(
            ['flare_choice', 'flare_choice_alt'],
            $registry->findNames(RegistryStubValue::class, RegistryCapableElement::class),
        );
        self::assertSame(
            ['flare_other'],
            $registry->findNames(RegistryOtherValue::class, RegistryCapableElement::class),
        );
    }

    /** §3.4: the form never names an element; it names a capability the element must implement. */
    public function testElectionFiltersByRequiresUsingInstanceof(): void
    {
        $registry = self::registry();

        // flare_choice requires the capability; flare_choice_alt does not.
        self::assertSame(
            ['flare_choice_alt'],
            $registry->findNames(RegistryStubValue::class, RegistryPlainElement::class),
        );

        // Accepts an instance as well as a class-string.
        self::assertSame(
            ['flare_choice_alt'],
            $registry->findNames(RegistryStubValue::class, new RegistryPlainElement()),
        );
        self::assertSame(
            ['flare_choice', 'flare_choice_alt'],
            $registry->findNames(RegistryStubValue::class, new RegistryCapableElement()),
        );
    }

    /** §5.3: an element declaring no value class has no forms, i.e. it is intrinsic-only. */
    public function testElectionYieldsNothingWithoutAValueClass(): void
    {
        self::assertSame([], self::registry()->findNames(null, RegistryCapableElement::class));
        self::assertNull(self::registry()->findDefaultName(null, RegistryCapableElement::class));
    }

    public function testDefaultElectionPrefersTheFlaggedFormRegardlessOfOrder(): void
    {
        $registry = self::registry();

        // flare_choice_alt is flagged default but registered second.
        self::assertSame(
            'flare_choice_alt',
            $registry->findDefaultName(RegistryStubValue::class, RegistryCapableElement::class),
        );
    }

    public function testDefaultElectionFallsBackToTheFirstEligibleForm(): void
    {
        $registry = new FilterFormRegistry(null, [
            'a' => ['value' => RegistryStubValue::class, 'requires' => [], 'default' => false, 'service' => 's.a'],
            'b' => ['value' => RegistryStubValue::class, 'requires' => [], 'default' => false, 'service' => 's.b'],
        ]);

        self::assertSame('a', $registry->findDefaultName(RegistryStubValue::class, RegistryPlainElement::class));
    }

    /**
     * The sharp edge in the election rules: a `default` form the element cannot satisfy is skipped
     * rather than winning and then failing, so the first *eligible* form takes over.
     */
    public function testDefaultElectionSkipsAnIneligibleDefault(): void
    {
        $registry = new FilterFormRegistry(null, [
            'needs_capability' => [
                'value' => RegistryStubValue::class,
                'requires' => [RegistryStubCapability::class],
                'default' => true,
                'service' => 's.a',
            ],
            'plain' => [
                'value' => RegistryStubValue::class,
                'requires' => [],
                'default' => false,
                'service' => 's.b',
            ],
        ]);

        self::assertSame('plain', $registry->findDefaultName(RegistryStubValue::class, RegistryPlainElement::class));
        self::assertSame(
            'needs_capability',
            $registry->findDefaultName(RegistryStubValue::class, RegistryCapableElement::class),
        );
    }

    /** The state of the tree before any form is registered: empty, valid, and never throwing. */
    public function testEmptyRegistryIsUsableAndSilent(): void
    {
        $registry = new FilterFormRegistry();

        self::assertSame([], $registry->keys());
        self::assertFalse($registry->has('anything'));
        self::assertNull($registry->getService('anything'));
        self::assertSame([], $registry->findNames(RegistryStubValue::class, RegistryCapableElement::class));
        self::assertNull($registry->findDefaultName(RegistryStubValue::class, RegistryCapableElement::class));
    }

    public function testGetServiceReturnsNullWhenTheLocatorYieldsTheWrongType(): void
    {
        $registry = new FilterFormRegistry(
            new ServiceLocator(['a' => static fn (): \stdClass => new \stdClass()]),
            ['a' => ['value' => null, 'requires' => [], 'default' => false, 'service' => 's.a']],
        );

        self::assertNull($registry->getService('a'));
    }

    public function testGetServiceThrowsWhenMetadataExistsWithoutALocator(): void
    {
        $registry = new FilterFormRegistry(null, [
            'a' => ['value' => null, 'requires' => [], 'default' => false, 'service' => 's.a'],
        ]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/no form locator was injected/');

        $registry->getService('a');
    }

    private static function registry(): FilterFormRegistry
    {
        return new FilterFormRegistry(null, [
            'flare_choice' => [
                'value' => RegistryStubValue::class,
                'requires' => [RegistryStubCapability::class],
                'default' => false,
                'service' => 'test.choice_form',
            ],
            'flare_choice_alt' => [
                'value' => RegistryStubValue::class,
                'requires' => [],
                'default' => true,
                'service' => 'test.choice_form_alt',
            ],
            'flare_other' => [
                'value' => RegistryOtherValue::class,
                'requires' => [],
                'default' => false,
                'service' => 'test.other_form',
            ],
        ]);
    }
}

interface RegistryStubCapability
{
}

final readonly class RegistryStubValue
{
}

final readonly class RegistryOtherValue
{
}

final class RegistryCapableElement implements RegistryStubCapability
{
}

final class RegistryPlainElement
{
}

final class RegistryFormStub implements FilterFormInterface
{
    public function buildForm(FilterFormBuilderInterface $builder, FilterContext $context): void
    {
    }

    public function decode(FormInterface $mount, FilterContext $context): ?object
    {
        return null;
    }
}
