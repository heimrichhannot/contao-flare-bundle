<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Form;

use HeimrichHannot\FlareBundle\Config\ComponentConfig;
use HeimrichHannot\FlareBundle\Filter\Control\FilterControlInterface;
use HeimrichHannot\FlareBundle\Filter\ControlBinding;
use HeimrichHannot\FlareBundle\Filter\ControlScope;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\ElementBinding;
use HeimrichHannot\FlareBundle\Filter\ElementScope;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Value\KeywordsValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
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
    private function listSpec(array $filters = []): ListSpec
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
        );
    }

    /**
     * Creates a control building its form via the given callable; it decodes to a KeywordsValue.
     *
     * @param callable(FilterFormBuilderInterface, ControlScope): void $buildForm
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

            public function buildForm(FilterFormBuilderInterface $builder, ControlScope $scope): void
            {
                ($this->buildForm)($builder, $scope);
            }

            public function decode(mixed $data, ControlScope $scope): ?ValueInterface
            {
                return KeywordsValue::tryFrom($data);
            }

            public function hydrate(?ValueInterface $value, ControlScope $scope): mixed
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
    ): Filter {
        $element ??= new class implements FilterElementInterface {
            public function buildContext(
                FilterContextBuilderInterface $builder,
                ElementScope                  $scope,
                ?ValueInterface               $value,
            ): void {}
        };

        return new Filter(
            element: new ElementBinding($element, 'test_element', new ComponentConfig([])),
            alias: $alias,
            control: $control !== null ? new ControlBinding(
                $control,
                $control::class,
                new ComponentConfig(['label' => null, 'placeholder' => null]),
            ) : null,
            value: $value,
        );
    }

    private function single(): FilterControlInterface
    {
        return $this->control(static function (FilterFormBuilderInterface $builder): void {
            $builder->single(TextType::class, ['required' => false]);
        });
    }
}
