<?php

namespace HeimrichHannot\FlareBundle\FilterElement;

use HeimrichHannot\FlareBundle\Filter\FilterDefinition;
use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\PaletteContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterCallback;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Enum\SqlEquationOperator;
use HeimrichHannot\FlareBundle\Util\DcaHelper;

#[AsFilterElement(alias: SimpleEquationElement::TYPE, isTargeted: true)]
class SimpleEquationElement implements PaletteContract
{
    public const TYPE = 'flare_equation_simple';

    /**
     * @throws FilterException
     */
    public function __invoke(FilterContext $context, FilterQueryBuilder $qb): void
    {
        $filterModel = $context->getFilterModel();

        if (!($operand = $filterModel->equationLeft)
            || !$op = SqlEquationOperator::match($filterModel->equationOperator))
        {
            throw new FilterException('Invalid filter configuration.');
        }

        $operand = $qb->column($operand);

        $where = match ($op) {
            SqlEquationOperator::EQUALS => $qb->expr()->eq($operand, ':eq_right'),
            SqlEquationOperator::NOT_EQUALS => $qb->expr()->neq($operand, ':eq_right'),
            SqlEquationOperator::GREATER_THAN => $qb->expr()->gt($operand, ':eq_right'),
            SqlEquationOperator::GREATER_THAN_EQUALS => $qb->expr()->gte($operand, ':eq_right'),
            SqlEquationOperator::LESS_THAN => $qb->expr()->lt($operand, ':eq_right'),
            SqlEquationOperator::LESS_THAN_EQUALS => $qb->expr()->lte($operand, ':eq_right'),
            SqlEquationOperator::LIKE => $qb->expr()->like($operand, ':eq_right'),
            SqlEquationOperator::NOT_LIKE => $qb->expr()->notLike($operand, ':eq_right'),
            SqlEquationOperator::IS_NULL => $qb->expr()->isNull($operand),
            SqlEquationOperator::IS_NOT_NULL => $qb->expr()->isNotNull($operand),
            default => null,
        };

        if (!$where) {
            throw new FilterException('Invalid filter configuration: Operator not supported.');
        }

        $qb->where($where);

        if (!$op->isUnary()) {
            $qb->setParameter(':eq_right', $filterModel->equationRight ?: '');
        }
    }

    #[AsFilterCallback(self::TYPE, 'fields.equationLeft.options')]
    public function getEquationLeftOptions(string $targetTable): array
    {
        return DcaHelper::getFieldOptions($targetTable);
    }

    public function getPalette(PaletteConfig $config): ?string
    {
        $filterModel = $config->getFilterModel();

        if (SqlEquationOperator::match($filterModel?->equationOperator)?->isUnary()) {
            return '{flare_simple_equation_legend},equationLeft,equationOperator';
        }

        return '{flare_simple_equation_legend},equationLeft,equationOperator,equationRight';
    }

    public static function define(
        string              $equationLeft,
        SqlEquationOperator $equationOperator,
        mixed               $equationRight = null,
    ): FilterDefinition {
        $definition = new FilterDefinition(
            alias: static::TYPE,
            title: 'Simple Equation',
            intrinsic: true,
        );

        $definition->equationLeft = $equationLeft;
        $definition->equationOperator = $equationOperator->value;
        $definition->equationRight = $equationRight;

        return $definition;
    }
}