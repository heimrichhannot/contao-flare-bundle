<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Type;

use HeimrichHannot\FlareBundle\Enum\SqlEquationOperator;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Query\FilterQueryBuilder;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SimpleEquationFilterType extends AbstractFilterType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('operand_left')
            ->info('The left operand of the equation filter')
            ->required()
            ->allowedTypes('string')
        ;

        $resolver->define('operator')
            ->info('The operator of the equation filter.')
            ->required()
            ->allowedTypes(SqlEquationOperator::class, 'string')
            ->allowedValues(static fn (SqlEquationOperator|string $value): bool => (bool) SqlEquationOperator::match($value))
            ->normalize(static fn (Options $resolver, SqlEquationOperator|string $value): ?SqlEquationOperator => SqlEquationOperator::match($value))
        ;

        $resolver->define('operand_right')
            ->info('The right operand of the equation filter (optional for unary operators).')
            ->allowedTypes('string', 'int', 'null')
            ->default('')
        ;
    }

    /**
     * @throws FilterException
     */
    public function buildQuery(FilterQueryBuilder $builder, array $options): void
    {
        $operandLeft = $options['operand_left'];
        $operator = SqlEquationOperator::match($options['operator']);

        if (!$operandLeft || !$operator instanceof SqlEquationOperator) {
            throw new FilterException('Invalid filter configuration.');
        }

        $operandLeft = $builder->column($operandLeft);

        $where = match ($operator) {
            SqlEquationOperator::EQUALS => $builder->expr()->eq($operandLeft, ':eq_right'),
            SqlEquationOperator::NOT_EQUALS => $builder->expr()->neq($operandLeft, ':eq_right'),
            SqlEquationOperator::GREATER_THAN => $builder->expr()->gt($operandLeft, ':eq_right'),
            SqlEquationOperator::GREATER_THAN_EQUALS => $builder->expr()->gte($operandLeft, ':eq_right'),
            SqlEquationOperator::LESS_THAN => $builder->expr()->lt($operandLeft, ':eq_right'),
            SqlEquationOperator::LESS_THAN_EQUALS => $builder->expr()->lte($operandLeft, ':eq_right'),
            SqlEquationOperator::LIKE => $builder->expr()->like($operandLeft, ':eq_right'),
            SqlEquationOperator::NOT_LIKE => $builder->expr()->notLike($operandLeft, ':eq_right'),
            SqlEquationOperator::IS_NULL => $builder->expr()->isNull($operandLeft),
            SqlEquationOperator::IS_NOT_NULL => $builder->expr()->isNotNull($operandLeft),
            default => null,
        };

        if (!$where) {
            throw new FilterException('Invalid filter configuration: Operator not supported.');
        }

        $builder->where($where);

        if (!$operator->isUnary()) {
            $operandRight = $options['operand_right'];
            $builder->setParameter(':eq_right', $operandRight);
        }
    }
}