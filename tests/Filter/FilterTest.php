<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter;

use HeimrichHannot\FlareBundle\Filter\Control\AbstractFilterControl;
use HeimrichHannot\FlareBundle\Filter\Control\FilterControlInterface;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterScope;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Tests\List\StubFilterElement;
use PHPUnit\Framework\TestCase;

final class FilterTest extends TestCase
{
    private static function filter(): Filter
    {
        static $element = null;

        return new Filter(
            element: $element ??= new StubFilterElement(),
            type: 'test',
            alias: 'foo',
            config: ['a' => 1],
            source: 'tl_flare_filter.1',
        );
    }

    private static function control(): FilterControlInterface
    {
        return new class extends AbstractFilterControl {
            public function buildForm(FilterFormBuilderInterface $builder, FilterScope $scope): void {}

            public function decode(mixed $data, FilterScope $scope): ?BoolValue
            {
                return null;
            }

            public function hydrate(?ValueInterface $value, FilterScope $scope): mixed
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
        self::assertSame('test', $targeted->type);
        self::assertSame('foo', $targeted->alias);
        self::assertSame(['a' => 1], $targeted->config);
        self::assertSame('tl_flare_filter.1', $targeted->source);

        self::assertFalse($filter->withTargetAlias(null)->targetingForced);
    }

    public function testWithersPreserveControlConfigAndValue(): void
    {
        $control = self::control();
        $value = new BoolValue(true);

        $filter = self::filter()
            ->withControl($control, ['b' => 2])
            ->withValue($value)
            ->withTargetAlias('translation');

        self::assertSame($control, $filter->control);
        self::assertSame(['b' => 2], $filter->controlConfig);
        self::assertSame($value, $filter->value);
        self::assertSame(['a' => 1], $filter->config);
        self::assertSame('tl_flare_filter.1', $filter->source);

        $withoutValue = $filter->withValue(null);

        self::assertNull($withoutValue->value);
        self::assertSame($control, $withoutValue->control);
        self::assertSame(['b' => 2], $withoutValue->controlConfig);
        self::assertSame('translation', $withoutValue->targetAlias);
        self::assertTrue($withoutValue->targetingForced);

        $withoutControl = $filter->withControl(null);

        self::assertNull($withoutControl->control);
        self::assertSame([], $withoutControl->controlConfig);
        self::assertSame($value, $withoutControl->value);
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
            'value' => null,
            'targetAlias' => null,
            'targetingForced' => false,
        ], $fingerprint);

        $changedConfig = new Filter(element: $filter->element, type: 'test', alias: 'foo', config: ['a' => 2]);

        self::assertNotSame($fingerprint, $changedConfig->fingerprint());
    }

    public function testFingerprintChangesWithControlAndValue(): void
    {
        $base = self::filter();
        $control = self::control();

        self::assertNotSame($base->fingerprint(), $base->withControl($control)->fingerprint());
        self::assertNotSame(
            $base->withControl($control)->fingerprint(),
            $base->withControl($control, ['b' => 2])->fingerprint(),
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
