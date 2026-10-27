<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Element;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilderInterface;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Enum\SqlEquationOperator;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilder;
use HeimrichHannot\FlareBundle\Filter\Predicate\SimpleEquationPredicate;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Util\DcaHelper;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[AsFilterElement(type: self::TYPE, isTargeted: true)]
class SimpleEquationFilterElement extends AbstractFilterElement
{
    public const TYPE = 'flare_equation_simple';

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('left')->default(null)->allowedTypes('string', 'null');
        $resolver->define('operator')->default(null)->allowedTypes(SqlEquationOperator::class, 'null');
        $resolver->define('right')->default(null);
    }

    protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void
    {
        $operator = $model->elm_equationOperator;

        $config
            ->set('left', $model->elm_equationLeft ?: null)
            ->set('operator', $operator ? SqlEquationOperator::match($operator) : null)
            ->set('right', $model->elm_equationRight);
    }

    /**
     * @throws FilterException
     */
    public function buildContext(FilterContextBuilder $builder, ?ValueInterface $value): void
    {
        $config = $builder->scope->filter->element->config->all();

        if (!($operand = $config['left']) || !($op = $config['operator'])) {
            throw new FilterException('Invalid filter configuration.');
        }

        $builder->addPredicate(SimpleEquationPredicate::class, [
            'operand_left' => $operand,
            'operator' => $op,
            'operand_right' => $config['right'],
        ]);
    }

    public function buildDca(DcaBuilderInterface $builder, DcaContext $context): void
    {
        $operatorValue = $context->filterModel?->elm_equationOperator;
        $operator = $operatorValue ? SqlEquationOperator::match($operatorValue) : null;

        $builder->palette($operator?->isUnary()
            ? '{flare_simple_equation_legend},elm_equationLeft,elm_equationOperator'
            : '{flare_simple_equation_legend},elm_equationLeft,elm_equationOperator,elm_equationRight');

        $builder->field('elm_equationLeft')
            ->options(static fn (): array => DcaHelper::getFieldOptions($context->getTargetTable()));
    }
}
