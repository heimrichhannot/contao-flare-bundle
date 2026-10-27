<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Form;

use HeimrichHannot\FlareBundle\Config\ComponentConfig;
use HeimrichHannot\FlareBundle\Engine\Context\ConfigurationContext;
use HeimrichHannot\FlareBundle\Filter\Control\KeywordsFilterControl;
use HeimrichHannot\FlareBundle\Filter\ControlBinding;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\ElementBinding;
use HeimrichHannot\FlareBundle\Filter\ElementScope;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterScope;
use HeimrichHannot\FlareBundle\Filter\Value\KeywordsValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Form\FilterMount;
use HeimrichHannot\FlareBundle\Form\FormHarness;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\ListSpec;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Forms;

final class FormHarnessTest extends TestCase
{
    public function testDecodeSkipsMountsWhoseChildWasRemoved(): void
    {
        $harness = $this->harness(['q' => 'foo'], remove: ['q']);

        self::assertSame([], $harness->decode());
        self::assertNull($harness->getChild('q'));
        self::assertNotNull($harness->getMount('q'));
    }

    public function testDecodeIsKeyedByAliasAndCoversEveryMountWhenSubmitted(): void
    {
        $harness = $this->harness(['a' => 'x', 'b' => 'y']);

        $harness->getForm()->submit(['a' => 'one', 'b' => '']);

        $values = $harness->decode();

        self::assertSame(['a', 'b'], \array_keys($values));
        self::assertSame('one', $values['a']->keywords);
        self::assertNull($values['b']);
    }

    public function testDecodeOmitsNullEntriesWhenUnsubmitted(): void
    {
        $harness = $this->harness(['a' => 'x', 'b' => null]);

        self::assertSame(['a'], \array_keys($harness->decode()));
    }

    /**
     * @param array<string, ?string> $initial Alias => initial keywords (null: no initial value).
     * @param list<string> $remove Aliases whose child is removed from the form after mounting.
     */
    private function harness(array $initial, array $remove = []): FormHarness
    {
        $driver = new class implements ListDriverInterface {
            public function resolveDcTable(string $type, array $config, array $attributes): string
            {
                return 'tl_test';
            }
        };

        $element = new class implements FilterElementInterface {
            public function buildContext(
                FilterContextBuilderInterface $builder,
                ElementScope                  $scope,
                ?ValueInterface               $value,
            ): void {}
        };

        $control = new KeywordsFilterControl();
        $list = new ListSpec(driver: $driver, type: 'test_list', dc: 'tl_test');
        $builder = Forms::createFormFactory()->createNamedBuilder('flare_test', FormType::class);
        $mounts = [];

        foreach ($initial as $alias => $keywords) {
            $filter = new Filter(
                element: new ElementBinding($element, 'test_element', new ComponentConfig([])),
                alias: $alias,
                control: $control !== null ? new ControlBinding(
                    $control,
                    $control::class,
                    new ComponentConfig(['label' => null, 'placeholder' => null]),
                ) : null,
                value: $keywords === null ? null : new KeywordsValue($keywords),
            );

            $builder->add($alias, TextType::class, ['required' => false, 'data' => $keywords]);
            $mounts[$alias] = new FilterMount($filter, $alias, new FilterScope($list, $filter, new ConfigurationContext()));
        }

        foreach ($remove as $alias) {
            $builder->remove($alias);
        }

        return new FormHarness($builder->getForm(), $mounts);
    }
}
