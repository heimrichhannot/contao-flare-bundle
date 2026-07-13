<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Element;

use Contao\StringUtil;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\Type\SearchKeywordsFilterType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[AsFilterElement(type: self::TYPE, isTargeted: true)]
class SearchKeywordsFilterElement extends AbstractFilterFilterElement
{
    public const TYPE = 'flare_search_keywords';

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('intrinsic')->default(false)->allowedTypes('bool');
        $resolver->define('columns')->default([])->allowedTypes('array');
        $resolver->define('prefill')->default(null)->allowedTypes('string', 'null');
        $resolver->define('label')->default(null)->allowedTypes('string', 'null');
        $resolver->define('placeholder')->default(null)->allowedTypes('string', 'null');
    }

    public function configFromRow(array $row): array
    {
        return [
            'intrinsic' => (bool) ($row['intrinsic'] ?? false),
            'columns' => StringUtil::deserialize($row['columnsGeneric'] ?? null, true),
            'prefill' => ($row['prefill'] ?? null) ?: null,
            'label' => ($row['label'] ?? null) ?: null,
            'placeholder' => ($row['placeholder'] ?? null) ?: null,
        ];
    }

    public function buildForm(FormBuilderInterface $builder, FilterContext $context): void
    {
        $config = $context->config;

        if ($config['intrinsic']) {
            return;
        }

        $options = [
            'label' => $config['label'] ?? 'label.text',
            'required' => false,
        ];

        if ($config['placeholder']) {
            $options['attr']['placeholder'] = $config['placeholder'];
        }

        $builder->add(FilterContext::FIELD_VALUE, TextType::class, $options);
    }

    public function buildFilter(FilterBuilderInterface $builder, FilterContext $context, array $data): void
    {
        $config = $context->config;

        $value = $config['intrinsic']
            ? $config['prefill']
            : ($data[FilterContext::FIELD_VALUE] ?? null);

        if (!$value || !\is_string($value)) {
            return;
        }

        if (!$columns = $config['columns']) {
            return;
        }

        $builder->add(SearchKeywordsFilterType::class, [
            'value' => $value,
            'columns' => $columns,
        ]);
    }

    public function buildDca(DcaBuilder $dca, DcaContext $context): void
    {
        $palette = '{filter_legend},columnsGeneric';

        $dca->palette($context->filterModel?->intrinsic
            ? $palette . ',prefill'
            : $palette . ';{form_legend},label,placeholder');
    }
}
