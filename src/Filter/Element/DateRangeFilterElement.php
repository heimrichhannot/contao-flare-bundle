<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Element;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilderInterface;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilder;
use HeimrichHannot\FlareBundle\Filter\Predicate\DateRangePredicate;
use HeimrichHannot\FlareBundle\Filter\Value\DateRangeValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[AsFilterElement(type: self::TYPE, value: DateRangeValue::class)]
class DateRangeFilterElement extends AbstractFilterElement
{
    public const TYPE = 'flare_dateRange';

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('field')->default(null)->allowedTypes('string', 'null');
    }

    protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void
    {
        $config->set('field', $model->elm_fieldGeneric ?: null);
    }

    /**
     * @throws FilterException
     */
    public function buildContext(FilterContextBuilder $builder, ?ValueInterface $value): void
    {
        $value = $this->expectValue($value, DateRangeValue::class);

        if (!$field = $builder->scope->filter->config['field'] ?? null) {
            throw new FilterException('Set elm_fieldGeneric in filter model.', method: __METHOD__);
        }

        if (!$value) {
            return;
        }

        $builder->addPredicate(DateRangePredicate::class, [
            'field' => $field,
            'from' => $value->from,
            'to' => $value->to,
        ]);
    }

    public function buildDca(DcaBuilderInterface $builder, DcaContext $context): void
    {
        $builder->palette('{filter_legend},elm_fieldGeneric');
    }
}
