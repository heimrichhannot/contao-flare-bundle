<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\FilterElement;

use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\FilterElement\FormDataContract;
use HeimrichHannot\FlareBundle\Contract\FilterElement\FormTypeOptionsContract;
use HeimrichHannot\FlareBundle\Contract\FilterElement\RuntimeValueContract;
use HeimrichHannot\FlareBundle\Contract\IsSupportedContract;
use HeimrichHannot\FlareBundle\Contract\PaletteContract;
use HeimrichHannot\FlareBundle\Event\FilterElementFormTypeOptionsEvent;
use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterInvocation;
use HeimrichHannot\FlareBundle\Form\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Specification\ConfiguredFilter;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use Symfony\Component\Form\FormInterface;

/**
 * @phpstan-template FormOptionsShape of array{
 *      expanded?: bool,
 *      label?: string,
 *      multiple?: bool,
 *      placeholder?: string
 *  }
 */
abstract class AbstractFilterElement implements FilterElementInterface,
    FormDataContract, FormTypeOptionsContract, IsSupportedContract, PaletteContract, RuntimeValueContract
{
    /**
     * @var FormOptionsShape|string[] Defines which filter-model fields to use for auto-generating form type options.
     */
    public static array $autoFormOptionsMap = [
        'multiple' => 'isMultiple',
        'expanded' => 'isExpanded',
        'required' => 'isMandatory',
        'mandatory' => 'isMandatory',
        'label' => 'label',
        'placeholder' => 'placeholder',
    ];

    /**
     * Creates default form type options based on default filter model fields and the given config.
     *
     * @param ConfiguredFilter $filter The filter definition.
     * @param array<string, mixed>|array<int, string>|array<int|string,
     *     mixed>|FormOptionsShape|list<key-of<FormOptionsShape>> $config The config to use.
     *
     * @return array
     *
     * @api Creates default form type options based on default filter model fields and the given config.
     * @example $config = ['label', 'multiple', 'placeholder' => 'Select a value']
     */
    public function defaultFormTypeOptions(
        ConfiguredFilter $filter,
        array            $config = [],
    ): array {
        $options = [];

        /** @var array<int, string> $listPart */
        $listPart = \array_filter($config, '\is_int', \ARRAY_FILTER_USE_KEY);

        foreach (self::$autoFormOptionsMap as $optionName => $attribute)
        {
            // Associative branch
            if (\array_key_exists($optionName, $config))
            {
                $value = $filter->{$attribute};
                $default = $config[$optionName];

                if ($value === '') {
                    $value = $default;
                }

                $option = $value ?? $default;

                if (!\is_null($option)) {
                    $options[$optionName] = $option;
                }

                continue;
            }

            // List branch
            if (\in_array($optionName, $listPart, true))
            {
                $options[$optionName] = $filter->{$attribute};
            }
        }

        return $options;
    }

    public function handleFormTypeOptions(FilterElementFormTypeOptionsEvent $event): void {}

    public function buildForm(FilterFormBuilderInterface $builder, FilterElementContext $context): void
    {
        if ($context->filter->isIntrinsic()) {
            return;
        }

        $builder->add($context);
    }

    public function buildFilter(FilterBuilderInterface $builder, FilterInvocation $invocation): void {}

    public function extractFormData(FormInterface $form): mixed
    {
        return $form->getData();
    }

    public function isSupported(): bool
    {
        return true;
    }

    public function getPalette(PaletteConfig $config): ?string
    {
        return null;
    }

    public function processRuntimeValue(mixed $value, ListSpecification $list, ConfiguredFilter $filter): mixed
    {
        return $value;
    }

    public static function define(): ConfiguredFilter
    {
        throw new \LogicException('Not implemented.');
    }
}