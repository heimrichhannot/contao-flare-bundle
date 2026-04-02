<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\FilterElement;

use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\FilterElement\FormTypeOptionsContract;
use HeimrichHannot\FlareBundle\Contract\FilterElement\RuntimeValueContract;
use HeimrichHannot\FlareBundle\Contract\IsSupportedContract;
use HeimrichHannot\FlareBundle\Contract\OptionsInterface;
use HeimrichHannot\FlareBundle\Contract\PaletteContract;
use HeimrichHannot\FlareBundle\Event\FilterElementFormTypeOptionsEvent;
use HeimrichHannot\FlareBundle\Filter\FilterInvocation;
use HeimrichHannot\FlareBundle\Filter\FilterInvokerInterface;
use HeimrichHannot\FlareBundle\Query\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Specification\FilterDefinition;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @phpstan-template FormOptionsShape of array{
 *      expanded?: bool,
 *      label?: string,
 *      multiple?: bool,
 *      placeholder?: string
 *  }
 */
abstract class AbstractFilterElement implements FilterInvokerInterface, OptionsInterface,
    FormTypeOptionsContract, IsSupportedContract, PaletteContract, RuntimeValueContract
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
     * The default filtering logic.
     *
     * {@inheritdoc}
     */
    abstract public function __invoke(FilterInvocation $inv, FilterQueryBuilder $qb): void;

    /**
     * Creates default form type options based on default filter model fields and the given config.
     *
     * @param FilterDefinition $filter The filter definition.
     * @param array<string, mixed>|array<int, string>|array<int|string, mixed>|FormOptionsShape|list<key-of<FormOptionsShape>> $config The config to use.
     *
     * @return array
     *
     * @api Creates default form type options based on default filter model fields and the given config.
     * @example $config = ['label', 'multiple', 'placeholder' => 'Select a value']
     */
    public function defaultFormTypeOptions(
        FilterDefinition $filter,
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

    public function isSupported(): bool
    {
        return true;
    }

    public function configureOptions(OptionsResolver $resolver): void {}

    public function getPalette(PaletteConfig $config): ?string
    {
        return null;
    }

    public function processRuntimeValue(mixed $value, ListSpecification $list, FilterDefinition $filter): mixed
    {
        return $value;
    }

    public static function define(): FilterDefinition
    {
        throw new \LogicException('Not implemented.');
    }
}