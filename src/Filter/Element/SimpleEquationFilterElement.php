<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Element;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilderInterface;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Enum\SqlEquationOperator;
use HeimrichHannot\FlareBundle\Filter\ElementScope;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilderInterface;
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
        $resolver->define('left')->required()->allowedTypes('string')
            ->allowedValues(static fn (string $value): bool => $value !== '');
        $resolver->define('operator')->required()->allowedTypes(SqlEquationOperator::class);
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

    public function buildContext(
        FilterContextBuilderInterface $builder,
        ElementScope                  $scope,
        ?ValueInterface               $value,
    ): void {
        $builder->addPredicate(SimpleEquationPredicate::class, [
            'operand_left' => $scope->get('left'),
            'operator' => $scope->get('operator'),
            'operand_right' => $scope->get('right'),
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
