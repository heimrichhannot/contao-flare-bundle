<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Registry;

use HeimrichHannot\FlareBundle\Filter\Control\AbstractFilterControl;
use HeimrichHannot\FlareBundle\Filter\Control\FilterControlInterface;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterScope;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use HeimrichHannot\FlareBundle\Filter\Value\ChoiceValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Registry\FilterControlRegistry;
use PHPUnit\Framework\TestCase;

final class FilterControlRegistryTest extends TestCase
{
    public function testFindNamesFiltersByValueClassInRegistrationOrder(): void
    {
        $r = (new FilterControlRegistry())
            ->add(self::control(), 'a', BoolValue::class)
            ->add(self::control(), 'b', ChoiceValue::class)
            ->add(self::control(), 'c', BoolValue::class);

        self::assertSame(['a', 'c'], $r->findNames(BoolValue::class));
        self::assertSame(['b'], $r->findNames(ChoiceValue::class));
        self::assertSame([], $r->findNames(\stdClass::class));
        self::assertSame(['a', 'b', 'c'], $r->keys());
        self::assertNull($r->get('nope'));
    }

    public function testLookupByName(): void
    {
        $control = self::control();
        $r = (new FilterControlRegistry())->add($control, 'a', BoolValue::class);

        self::assertTrue($r->has('a'));
        self::assertFalse($r->has('b'));
        self::assertSame($control, $r->get('a'));
        self::assertSame(BoolValue::class, $r->getValueClass('a'));
        self::assertNull($r->getValueClass('b'));
        self::assertNull($r->get(null));
        self::assertNull($r->getValueClass(null));
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
}
