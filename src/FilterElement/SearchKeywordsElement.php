<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\FilterElement;

use Contao\StringUtil;
use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\FilterElement\IntrinsicValueContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Event\FilterElementFormTypeOptionsEvent;
use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterInvocation;
use HeimrichHannot\FlareBundle\Filter\Type\SearchKeywordsFilterType;
use HeimrichHannot\FlareBundle\Specification\ConfiguredFilter;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use Symfony\Component\Form\Extension\Core\Type\TextType;

#[AsFilterElement(
    type: self::TYPE,
    formType: TextType::class,
    isTargeted: true,
)]
class SearchKeywordsElement extends AbstractFilterElement implements IntrinsicValueContract
{
    public const TYPE = 'flare_search_keywords';

    public function buildFilter(FilterBuilderInterface $builder, FilterInvocation $invocation): void
    {
        $filter = $invocation->filter;
        $value = $filter->isIntrinsic()
            ? $this->getIntrinsicValue($invocation->list, $filter)
            : $invocation->getValue();

        if (!$value || !\is_string($value)) {
            return;
        }

        if (!$columns = StringUtil::deserialize($filter->columnsGeneric, true)) {
            return;
        }

        $builder->add(SearchKeywordsFilterType::class, [
            'value' => $value,
            'columns' => $columns,
        ]);
    }

    public function handleFormTypeOptions(FilterElementFormTypeOptionsEvent $event): void
    {
        $event->options['label'] = 'label.text';
        $event->options['required'] = false;

        if ($label = $event->filter->label) {
            $event->options['label'] = $label;
        }

        if ($placeholder = $event->filter->placeholder) {
            $event->options['attr']['placeholder'] = $placeholder;
        }
    }

    public function getIntrinsicValue(ListSpecification $list, ConfiguredFilter $filter): ?string
    {
        return $filter->prefill ?: null;
    }

    public function getPalette(PaletteConfig $config): ?string
    {
        $palette = '{filter_legend},columnsGeneric';

        if ($config->getFilterModel()->intrinsic) {
            return $palette . ',prefill';
        }

        return $palette . ';{form_legend},label,placeholder';
    }
}
