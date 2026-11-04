<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Form;

use HeimrichHannot\FlareBundle\Config\ComponentConfig;
use HeimrichHannot\FlareBundle\Filter\ControlBinding;
use HeimrichHannot\FlareBundle\Filter\Control\FilterControlInterface;
use HeimrichHannot\FlareBundle\Filter\ControlContext;
use HeimrichHannot\FlareBundle\Filter\ElementBinding;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\ElementContext;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\FilterEffectBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Value\KeywordsValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Form\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\ListSpec;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * Filters with stub controls and the list holding them, for the form harness and the filter form invoker.
 */
trait BuildsFormFixturesTrait
{
    /**
     * @param list<Filter> $filters
     */
    private function listSpec(array $filters = [], ?string $source = null): ListSpec
    {
        $driver = new class implements ListDriverInterface {
            public function resolveDcTable(string $type, array $config, array $attributes): string
            {
                return (string) ($config['dc'] ?? '');
            }
        };

        return new ListSpec(
            driver: $driver,
            type: 'test_list',
            dc: 'tl_test',
            filters: $filters,
            source: $source,
        );
    }

    /**
     * Creates a control building its form via the given callable; it decodes to a KeywordsValue.
     *
     * @param callable(FilterFormBuilderInterface, ControlContext): void $buildForm
     */
    private function control(callable $buildForm): FilterControlInterface
    {
        return new class($buildForm) implements FilterControlInterface {
            /** @var callable */
            private $buildForm;

            public function __construct(callable $buildForm)
            {
                $this->buildForm = $buildForm;
            }

            public function buildForm(FilterFormBuilderInterface $builder, ControlContext $context): void
            {
                ($this->buildForm)($builder, $context);
            }

            public function decode(mixed $data, ControlContext $context): ?ValueInterface
            {
                return KeywordsValue::tryFrom($data);
            }

            public function hydrate(?ValueInterface $value, ControlContext $context): mixed
            {
                return null;
            }
        };
    }

    private function filter(
        string                  $alias,
        ?FilterControlInterface $control,
        ?ValueInterface         $value = null,
        ?FilterElementInterface $element = null,
        ?string                 $source = null,
    ): Filter {
        $element ??= new class implements FilterElementInterface {
            public function buildEffect(
                FilterEffectBuilderInterface $builder,
                ElementContext               $context,
                ?ValueInterface              $value,
            ): void {}
        };

        return new Filter(
            element: new ElementBinding($element, 'test_element', new ComponentConfig([]), $value !== null ? $value::class : null),
            alias: $alias,
            control: $control !== null ? new ControlBinding(
                $control,
                $control::class,
                new ComponentConfig(['label' => null, 'placeholder' => null]),
            ) : null,
            value: $value,
            source: $source,
        );
    }

    private function single(): FilterControlInterface
    {
        return $this->control(static function (FilterFormBuilderInterface $builder): void {
            $builder->single(TextType::class, ['required' => false]);
        });
    }
}
