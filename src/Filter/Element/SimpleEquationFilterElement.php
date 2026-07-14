<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Element;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Enum\SqlEquationOperator;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\Type\SimpleEquationFilterType;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Util\DcaHelper;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[AsFilterElement(type: self::TYPE, intrinsicOnly: true, isTargeted: true)]
class SimpleEquationFilterElement extends AbstractFilterElement
{
    public const TYPE = 'flare_equation_simple';

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('intrinsic')->default(false)->allowedTypes('bool');
        $resolver->define('left')->default(null)->allowedTypes('string', 'null');
        $resolver->define('operator')->default(null)->allowedTypes(SqlEquationOperator::class, 'null');
        $resolver->define('right')->default(null);
    }

    protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void
    {
        $config
            ->set('intrinsic', (bool) $model->intrinsic)
            ->set('left', $model->equationLeft ?: null)
            ->set('operator', $model->equationOperator ? SqlEquationOperator::match($model->equationOperator) : null)
            ->set('right', $model->equationRight);
    }

    /**
     * @throws FilterException
     */
    public function buildFilter(FilterBuilderInterface $builder, FilterContext $context, array $values): void
    {
        $config = $context->config;

        if (!($operand = $config['left']) || !($op = $config['operator'])) {
            throw new FilterException('Invalid filter configuration.');
        }

        $builder->add(SimpleEquationFilterType::class, [
            'operand_left' => $operand,
            'operator' => $op,
            'operand_right' => $config['right'],
        ]);
    }

    public function buildDca(DcaBuilder $dca, DcaContext $context): void
    {
        $operatorValue = $context->filterModel?->equationOperator;
        $operator = $operatorValue ? SqlEquationOperator::match($operatorValue) : null;

        $dca->palette($operator?->isUnary()
            ? '{flare_simple_equation_legend},equationLeft,equationOperator'
            : '{flare_simple_equation_legend},equationLeft,equationOperator,equationRight');

        $dca->field('equationLeft')
            ->options(static fn (): array => DcaHelper::getFieldOptions($context->getTargetTable()));
    }

}
