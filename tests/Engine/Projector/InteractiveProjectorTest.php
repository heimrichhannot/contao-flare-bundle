<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Engine\Projector;

use HeimrichHannot\FlareBundle\Engine\Projector\InteractiveProjector;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\List\ListSpec;
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
        return new ListSpec(type: 'test', dc: 'tl_test', filters: [
            $key => new Filter(element: 'test_element', alias: $alias),
        ]);
    }

    public function testFlatSubmittedValueIsKeyedCanonically(): void
    {
        $root = $this->createRootBuilder();
        $this->addFlatChild($root, 'suche');
        $form = $root->getForm();

        $form->submit(['suche' => 'term']);

        $this->assertSame(
            ['sucheKey' => [FilterContext::SINGLE_VALUE => 'term']],
            $this->collect($this->listWithFilter('sucheKey', 'suche'), $form),
        );
    }

    public function testFlatUnsubmittedDefaultIsCollected(): void
    {
        $root = $this->createRootBuilder();
        $this->addFlatChild($root, 'suche', ['data' => 'preset']);
        $form = $root->getForm();

        $this->assertSame(
            ['sucheKey' => [FilterContext::SINGLE_VALUE => 'preset']],
            $this->collect($this->listWithFilter('sucheKey', 'suche'), $form),
        );
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

        $this->assertSame(
            ['sucheKey' => [FilterContext::SINGLE_VALUE => null]],
            $this->collect($this->listWithFilter('sucheKey', 'suche'), $form),
        );
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

        $this->assertSame(
            ['rangeKey' => ['from' => 'a', 'to' => 'b']],
            $this->collect($this->listWithFilter('rangeKey', 'range'), $form),
        );
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

        $this->assertSame(
            ['rangeKey' => ['from' => 'a']],
            $this->collect($this->listWithFilter('rangeKey', 'range'), $form),
        );
    }

    public function testFilterWithoutMountedChildIsSkipped(): void
    {
        $form = $this->createRootBuilder()->getForm();

        $this->assertSame([], $this->collect($this->listWithFilter('key', 'missing'), $form));
    }
}
