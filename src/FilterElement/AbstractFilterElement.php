<?php

namespace HeimrichHannot\FlareBundle\FilterElement;

use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\FilterElement\FormTypeOptionsContract;
use HeimrichHannot\FlareBundle\Contract\IsSupportedContract;
use HeimrichHannot\FlareBundle\Contract\PaletteContract;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterDefinition;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilder;

/**
 * @phpstan-template FormOptionsShape of array{
 *      expanded?: bool,
 *      label?: string,
 *      multiple?: bool,
 *      placeholder?: string
 *  }
 */
abstract class AbstractFilterElement implements FormTypeOptionsContract, IsSupportedContract, PaletteContract
{
    /**
     * @var FormOptionsShape|string[] Defines which filter-model fields to use for auto-generating form type options.
     */
    public static array $autoFormOptionsMap = [
        'multiple' => 'isMultiple',
        'expanded' => 'isExpanded',
        'label' => 'label',
        'placeholder' => 'placeholder',
    ];

    /**
     * Creates default form type options based on default filter model fields and the given config.
     *
     * @param FilterContext $context The filter context.
     * @param array<string, mixed>|array<int, string>|array<int|string, mixed> $config The config to use.
     * @phpstan-param FormOptionsShape|list<key-of<FormOptionsShape>> $config
     *
     * @return array
     *
     * @api Creates default form type options based on default filter model fields and the given config.
     * @example $config = ['label', 'multiple', 'placeholder' => 'Select a value']
     */
    public function defaultFormTypeOptions(
        FilterContext $context,
        array         $config = [],
    ): array {
        $filterModel = $context->getFilterModel();

        $options = [];

        /** @var array<string, true> $listPart */
        $listPart = \array_filter($config, '\is_int', \ARRAY_FILTER_USE_KEY);

        foreach (self::$autoFormOptionsMap as $optionName => $attribute)
        {
            if (\array_key_exists($optionName, $config))
            {
                $value = $filterModel->{$attribute};

                if ($value === '') {
                    $value = $config[$optionName];
                }

                $options[$optionName] = $value ?? $config[$optionName];
                continue;
            }

            if (\in_array($optionName, $listPart, true))
            {
                $options[$optionName] = $filterModel->{$attribute};
            }
        }

        return $options;
    }

    public function getFormTypeOptions(FilterContext $context, ChoicesBuilder $choices): array
    {
        return [];
    }

    public function isSupported(): bool
    {
        return true;
    }

    public function getPalette(PaletteConfig $config): ?string
    {
        return null;
    }

    public static function define(): FilterDefinition
    {
        throw new \LogicException('Not implemented.');
    }
}