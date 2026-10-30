<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Registry;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Filter\Control\AbstractFilterControl;
use HeimrichHannot\FlareBundle\Filter\Control\FilterControlInterface;
use HeimrichHannot\FlareBundle\Filter\ControlScope;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use HeimrichHannot\FlareBundle\Filter\Value\ChoiceValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Form\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Registry\FilterControlRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class FilterControlRegistryTest extends TestCase
{
    public function testFindTypesFiltersByValueClassInRegistrationOrder(): void
    {
        $r = (new FilterControlRegistry())
            ->add(self::control(), 'a', BoolValue::class)
            ->add(self::control(), 'b', ChoiceValue::class)
            ->add(self::control(), 'c', BoolValue::class);

        self::assertSame(['a', 'c'], $r->findTypes(BoolValue::class));
        self::assertSame(['b'], $r->findTypes(ChoiceValue::class));
        self::assertSame([], $r->findTypes(\stdClass::class));
        self::assertSame(['a', 'b', 'c'], $r->keys());
        self::assertNull($r->get('nope'));
    }

    public function testRejectsEmptyType(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new FilterControlRegistry())->add(self::control(), '', BoolValue::class);
    }

    public function testLookupByType(): void
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
}
