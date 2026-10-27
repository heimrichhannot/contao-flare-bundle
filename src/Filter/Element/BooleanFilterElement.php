<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Element;

use Contao\Controller;
use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilderInterface;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilder;
use HeimrichHannot\FlareBundle\Filter\Predicate\BooleanPredicate;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[AsFilterElement(type: self::TYPE, isTargeted: true, value: BoolValue::class)]
class BooleanFilterElement extends AbstractFilterElement
{
    public const TYPE = 'flare_bool';

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
        $value = $this->expectValue($value, BoolValue::class);

        if (!$field = $builder->scope->filter->element->config->all()['field'] ?? null) {
            $builder->abort();
        }

        if (!$value) {
            return;
        }

        $builder->addPredicate(BooleanPredicate::class, [
            'field' => $field,
            'value' => $value->state,
        ]);
    }

    public function buildDca(DcaBuilderInterface $builder, DcaContext $context): void
    {
        $builder->palette('{filter_legend},elm_fieldGeneric');

        $builder->field('elm_fieldGeneric')
            ->options(fn (): array => $this->getFieldGenericOptions($context->getTargetTable()));
    }

    protected function getFieldGenericOptions(string $targetTable): array
    {
        Controller::loadDataContainer($targetTable);

        if (!isset($GLOBALS['TL_DCA'][$targetTable]['fields'])) {
            return [];
        }

        $cbx = 'Checkbox';
        $non = 'Non-Checkbox';

        $options = [
            $cbx => [], // checkbox fields
            $non => [], // non-checkbox fields
        ];

        foreach ($GLOBALS['TL_DCA'][$targetTable]['fields'] as $name => $field)
        {
            $group = ('checkbox' === ($field['inputType'] ?? null)) ? $cbx : $non;
            $options[$group][$name] = $targetTable . '.' . $name;
        }

        \asort($options[$cbx]);
        \asort($options[$non]);

        return $options;
    }
}
