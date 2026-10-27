<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter;

use HeimrichHannot\FlareBundle\Config\ComponentConfig;
use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Filter\Control\AbstractFilterControl;
use HeimrichHannot\FlareBundle\Filter\Control\FilterControlInterface;
use HeimrichHannot\FlareBundle\Filter\ControlBinding;
use HeimrichHannot\FlareBundle\Filter\ControlScope;
use HeimrichHannot\FlareBundle\Filter\ElementBinding;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Tests\List\StubFilterElement;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class FilterTest extends TestCase
{
    private static function filter(): Filter
    {
        static $element = null;

        return new Filter(
            element: new ElementBinding(
                component: $element ??= new StubFilterElement(),
                type: 'test',
                config: new ComponentConfig(['a' => 1]),
            ),
            alias: 'foo',
            source: 'tl_flare_filter.1',
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function binding(
        ?FilterControlInterface $control = null,
        array                   $config = [],
        string                  $type = 'app_control',
    ): ControlBinding {
        return new ControlBinding($control ?? self::control(), $type, new ComponentConfig($config));
    }

    private static function control(): FilterControlInterface
    {
        return new class extends AbstractFilterControl {
            public function configureOptions(OptionsResolver $resolver): void {}

            protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void {}

            public function buildForm(FilterFormBuilderInterface $builder, ControlScope $scope): void {}

            public function decode(mixed $data, ControlScope $scope): ?BoolValue
            {
                return null;
            }

            public function hydrate(?ValueInterface $value, ControlScope $scope): mixed
            {
                return null;
            }
        };
    }

    public function testWithersPreserveOtherFields(): void
    {
        $filter = self::filter();

        $targeted = $filter->withTargetAlias('translation');

        self::assertSame('translation', $targeted->targetAlias);
        self::assertTrue($targeted->targetingForced);
        self::assertFalse($filter->targetingForced);
        self::assertSame($filter->element, $targeted->element);
        self::assertSame('foo', $targeted->alias);
        self::assertSame('tl_flare_filter.1', $targeted->source);

        self::assertFalse($filter->withTargetAlias(null)->targetingForced);
    }

    public function testWithersPreserveControlAndValue(): void
    {
        $control = self::binding(config: ['b' => 2]);
        $value = new BoolValue(true);

        $filter = self::filter()
            ->withControl($control)
            ->withValue($value)
            ->withTargetAlias('translation');

        self::assertSame($control, $filter->control);
        self::assertSame($value, $filter->value);
        self::assertSame(['a' => 1], $filter->element->config->all());
        self::assertSame('tl_flare_filter.1', $filter->source);

        $withoutValue = $filter->withValue(null);

        self::assertNull($withoutValue->value);
        self::assertSame($control, $withoutValue->control);
        self::assertSame('translation', $withoutValue->targetAlias);
        self::assertTrue($withoutValue->targetingForced);

        $withoutControl = $filter->withControl(null);

        self::assertNull($withoutControl->control);
        self::assertSame($value, $withoutControl->value);
    }

    public function testADroppedControlIsRecordedUntilAControlIsBound(): void
    {
        $dropped = self::filter()->withControl(self::binding())->withDroppedControl();

        self::assertNull($dropped->control);
        self::assertTrue($dropped->controlDropped);
        self::assertTrue($dropped->withValue(null)->controlDropped);
        self::assertTrue($dropped->withTargetAlias('translation')->controlDropped);
        self::assertFalse($dropped->withControl(self::binding())->controlDropped);
        self::assertFalse(self::filter()->controlDropped);
        self::assertNotSame(self::filter()->fingerprint(), self::filter()->withDroppedControl()->fingerprint());
    }

    public function testControlBindingCarriesTypeAndConfig(): void
    {
        $filter = self::filter()->withControl(self::binding(config: ['b' => 2]));

        self::assertSame('app_control', $filter->control?->type);
        self::assertSame(2, $filter->control?->config->get('b'));
        self::assertSame('app_control', $filter->withValue(new BoolValue(true))->control?->type);
        self::assertSame('app_control', $filter->withTargetAlias('translation')->control?->type);
    }

    public function testFingerprintKeysAreUnchanged(): void
    {
        $fingerprint = self::filter()->fingerprint();

        self::assertSame([
            'element',
            'type',
            'alias',
            'config',
            'control',
            'controlConfig',
            'controlType',
            'value',
            'targetAlias',
            'targetingForced',
            'controlDropped',
        ], \array_keys($fingerprint));
        self::assertSame(['a' => 1], $fingerprint['config']);
    }

    public function testFingerprintReflectsIdentityAndContent(): void
    {
        $filter = self::filter();

        $fingerprint = $filter->fingerprint();

        self::assertSame([
            'element' => StubFilterElement::class,
            'type' => 'test',
            'alias' => 'foo',
            'config' => ['a' => 1],
            'control' => null,
            'controlConfig' => [],
            'controlType' => null,
            'value' => null,
            'targetAlias' => null,
            'targetingForced' => false,
            'controlDropped' => false,
        ], $fingerprint);

        $changedConfig = new Filter(
            element: new ElementBinding($filter->element->component, 'test', new ComponentConfig(['a' => 2])),
            alias: 'foo',
        );

        self::assertNotSame($fingerprint, $changedConfig->fingerprint());
    }

    public function testFingerprintChangesWithControlAndValue(): void
    {
        $base = self::filter();
        $control = self::control();

        self::assertNotSame($base->fingerprint(), $base->withControl(self::binding($control))->fingerprint());
        self::assertNotSame(
            $base->withControl(self::binding($control))->fingerprint(),
            $base->withControl(self::binding($control, ['b' => 2]))->fingerprint(),
        );
        self::assertNotSame(
            $base->withControl(self::binding($control))->fingerprint(),
            $base->withControl(self::binding($control, type: 'other'))->fingerprint(),
        );
        self::assertNotSame($base->fingerprint(), $base->withValue(new BoolValue(true))->fingerprint());
        self::assertNotSame(
            $base->withValue(new BoolValue(true))->fingerprint(),
            $base->withValue(new BoolValue(false))->fingerprint(),
        );
        self::assertSame(
            $base->withValue(new BoolValue(true))->fingerprint(),
            $base->withValue(new BoolValue(true))->fingerprint(),
        );
    }
}
