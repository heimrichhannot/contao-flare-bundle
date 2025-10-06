<?php

namespace HeimrichHannot\FlareBundle\FilterElement;

use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\FilterElement\FormTypeOptionsContract;
use HeimrichHannot\FlareBundle\Contract\IsSupportedContract;
use HeimrichHannot\FlareBundle\Contract\PaletteContract;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilder;

abstract class AbstractFilterElement implements FormTypeOptionsContract, IsSupportedContract, PaletteContract
{
    /**
     * Creates default form type options based on default filter model fields and the given config.
     *
     * @param FilterContext $context
     * @param array{
     *     multiple?: bool,
     *     expanded?: bool,
     *     placeholder?: bool,
     * } $config
     * @return array
     * @api Creates default form type options based on default filter model fields and the given config.
     */
    public function defaultFormTypeOptions(
        FilterContext $context,
        array         $config = [],
    ): array {
        $filterModel = $context->getFilterModel();

        $options = [];

        if ($config['multiple'] ?? false) {
            $options['multiple'] = $filterModel->isMultiple;
        }

        if ($config['expanded'] ?? false) {
            $options['expanded'] = $filterModel->isExpanded;
        }

        if ($config['placeholder'] ?? false) {
            $options['placeholder'] = $filterModel->placeholder ?: 'empty_option.select';
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
}