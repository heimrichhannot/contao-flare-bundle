<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Engine\Projector;

use HeimrichHannot\FlareBundle\Engine\Projector\InteractiveProjector;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilder;
use HeimrichHannot\FlareBundle\Filter\FormulaBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterData;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;

final class InteractiveProjectorTest extends TestCase
{
    /**
     * collectFilterData() touches no constructor dependencies, so the test double
     * skips the parent constructor entirely.
     *
     * @return array<string|int, FilterData>
     */
    private function collect(ListSpec $list, FormInterface $form): array
    {
        $projector = new class extends InteractiveProjector {
            public function __construct() {}

            public function collect(ListSpec $list, FormInterface $form): array
            {
                return $this->collectFilterData($list, $form);
            }
        };

        return $projector->collect($list, $form);
    }

    private function createRootBuilder(): FormBuilderInterface
    {
        return Forms::createFormFactory()->createNamedBuilder('f', FormType::class);
    }

    private function addFlatChild(FormBuilderInterface $root, string $alias, array $options = []): void
    {
        $child = $root->create($alias, TextType::class, $options);
        $child->setAttribute(FilterContext::ATTR_SINGLE_FIELD, true);
        $root->add($child);
    }

    private function listWithFilter(string $key, string $alias): ListSpec
    {
        $driver = new class implements ListDriverInterface {
            public function resolveDcTable(string $type, array $config, array $attributes): string
            {
                return (string) ($config['dc'] ?? '');
            }
        };

        $element = new class implements FilterElementInterface {
            public function buildForm(FilterFormBuilderInterface $builder, FilterContext $context): void {}

            public function buildContext(
                FilterContextBuilder $builder,
                ?ValueInterface      $value,
            ): void {}
        };

        return new ListSpec(driver: $driver, type: 'test_list', dc: 'tl_test', filters: [
            $key => new Filter(element: $element, type: 'test_element', alias: $alias),
        ]);
    }

    public function testFlatSubmittedValueBecomesSingleData(): void
    {
        $root = $this->createRootBuilder();
        $this->addFlatChild($root, 'suche');
        $form = $root->getForm();

        $form->submit(['suche' => 'term']);

        $data = $this->collect($this->listWithFilter('sucheKey', 'suche'), $form);

        $this->assertSame(['sucheKey'], \array_keys($data));
        $this->assertTrue($data['sucheKey']->hasSingle());
        $this->assertSame('term', $data['sucheKey']->getSingleValue());
        $this->assertSame([], $data['sucheKey']->all());
    }

    public function testFlatUnsubmittedDefaultIsCollected(): void
    {
        $root = $this->createRootBuilder();
        $this->addFlatChild($root, 'suche', ['data' => 'preset']);
        $form = $root->getForm();

        $data = $this->collect($this->listWithFilter('sucheKey', 'suche'), $form);

        $this->assertSame(['sucheKey'], \array_keys($data));
        $this->assertSame('preset', $data['sucheKey']->getSingleValue());
    }

    public function testFlatUnsubmittedWithoutDefaultStaysUnset(): void
    {
        $root = $this->createRootBuilder();
        $this->addFlatChild($root, 'suche');
        $form = $root->getForm();

        $this->assertSame([], $this->collect($this->listWithFilter('sucheKey', 'suche'), $form));
    }

    public function testFlatSubmittedEmptyValueIsKeptSoItOverridesDataBags(): void
    {
        $root = $this->createRootBuilder();
        $this->addFlatChild($root, 'suche', ['data' => 'preset']);
        $form = $root->getForm();

        $form->submit(['suche' => '']);

        $data = $this->collect($this->listWithFilter('sucheKey', 'suche'), $form);

        $this->assertSame(['sucheKey'], \array_keys($data));
        // The submitted null must stay distinguishable from "never submitted".
        $this->assertTrue($data['sucheKey']->hasSingle());
        $this->assertNull($data['sucheKey']->getSingleValue());
    }

    public function testCompoundSubmittedDataIsCollected(): void
    {
        $root = $this->createRootBuilder();
        $root->add(
            $root->create('range', FormType::class, ['inherit_data' => false])
                ->add('from', TextType::class)
                ->add('to', TextType::class),
        );
        $form = $root->getForm();

        $form->submit(['range' => ['from' => 'a', 'to' => 'b']]);

        $data = $this->collect($this->listWithFilter('rangeKey', 'range'), $form);

        $this->assertSame(['rangeKey'], \array_keys($data));
        $this->assertFalse($data['rangeKey']->hasSingle());
        $this->assertSame(['from' => 'a', 'to' => 'b'], $data['rangeKey']->all());
    }

    public function testCompoundUnsubmittedFieldDefaultsAreCollected(): void
    {
        $root = $this->createRootBuilder();
        $root->add(
            $root->create('range', FormType::class, ['inherit_data' => false])
                ->add('from', TextType::class, ['data' => 'a'])
                ->add('to', TextType::class),
        );
        $form = $root->getForm();

        $data = $this->collect($this->listWithFilter('rangeKey', 'range'), $form);

        $this->assertSame(['rangeKey'], \array_keys($data));
        $this->assertSame(['from' => 'a'], $data['rangeKey']->all());
    }

    public function testFilterWithoutMountedChildIsSkipped(): void
    {
        $form = $this->createRootBuilder()->getForm();

        $this->assertSame([], $this->collect($this->listWithFilter('key', 'missing'), $form));
    }
}
