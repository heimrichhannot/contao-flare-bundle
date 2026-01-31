<?php

namespace HeimrichHannot\FlareBundle\FilterElement;

use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterCallback;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Enum\SqlEquationOperator;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\FilterInvocation;
use HeimrichHannot\FlareBundle\Query\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Specification\FilterDefinition;
use HeimrichHannot\FlareBundle\Util\DcaHelper;

#[AsFilterElement(type: self::TYPE, isTargeted: true)]
class SimpleEquationElement extends AbstractFilterElement
{
    public const TYPE = 'flare_equation_simple';

    /**
     * @throws FilterException
     */
    public function __invoke(FilterInvocation $inv, FilterQueryBuilder $qb): void
    {
        if (!($operand = $inv->filter->equationLeft)
            || !$op = SqlEquationOperator::match($inv->filter->equationOperator))
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
            $qb->setParameter(':eq_right', $inv->filter->equationRight ?: '');
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
        ?string              $equationLeft = null,
        ?SqlEquationOperator $equationOperator = null,
        mixed                $equationRight = null,
    ): FilterDefinition {
        $definition = new FilterDefinition(
            type: static::TYPE,
            title: 'Simple Equation',
            intrinsic: true,
        );

        if (!$equationLeft || !$equationOperator || (!$equationOperator->isUnary() && $equationRight === null)) {
            throw new FlareException('Invalid filter definition for SimpleEquationElement.');
        }

        $definition->equationLeft = $equationLeft;
        $definition->equationOperator = $equationOperator->value;
        $definition->equationRight = $equationRight;

        return $definition;
    }
}