<?php

namespace HeimrichHannot\FlareBundle\Filter\Element;

use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\PaletteContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterCallback;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Util\DBEquationOperator;
use HeimrichHannot\FlareBundle\Util\DcaHelper;

#[AsFilterElement(alias: SimpleEquation::TYPE)]
class SimpleEquation implements PaletteContract
{
    public const TYPE = 'flare_equation_simple';

    /**
     * @throws FilterException
     */
    public function __invoke(FilterContext $context, FilterQueryBuilder $qb): void
    {
        $filterModel = $context->getFilterModel();

        if (!$filterModel->equationLeft || !$op = DBEquationOperator::match($filterModel->equationOperator)) {
            throw new FilterException('Invalid filter configuration.');
        }

        $where = match ($op) {
            DBEquationOperator::EQUALS => $qb->expr()->eq($filterModel->equationLeft, ':eq_right'),
            DBEquationOperator::NOT_EQUALS => $qb->expr()->neq($filterModel->equationLeft, ':eq_right'),
            DBEquationOperator::GREATER_THAN => $qb->expr()->gt($filterModel->equationLeft, ':eq_right'),
            DBEquationOperator::GREATER_THAN_EQUALS => $qb->expr()->gte($filterModel->equationLeft, ':eq_right'),
            DBEquationOperator::LESS_THAN => $qb->expr()->lt($filterModel->equationLeft, ':eq_right'),
            DBEquationOperator::LESS_THAN_EQUALS => $qb->expr()->lte($filterModel->equationLeft, ':eq_right'),
            DBEquationOperator::LIKE => $qb->expr()->like($filterModel->equationLeft, ':eq_right'),
            DBEquationOperator::NOT_LIKE => $qb->expr()->notLike($filterModel->equationLeft, ':eq_right'),
            DBEquationOperator::IS_NULL => $qb->expr()->isNull($filterModel->equationLeft),
            DBEquationOperator::IS_NOT_NULL => $qb->expr()->isNotNull($filterModel->equationLeft),
            default => null,
        };

        if (!$where) {
            throw new FilterException('Invalid filter configuration: Operator not supported.');
        }

        $qb->where($where);

        if (!$op->isUnary()) {
            $qb->bind(':eq_right', $filterModel->equationRight ?: '');
        }
    }

    #[AsFilterCallback(self::TYPE, 'fields.equationLeft.options')]
    public function getEquationLeftOptions(ListModel $listModel): array
    {
        if (!$listModel->dc) {
            return [];
        }

        return DcaHelper::getFieldOptions($listModel->dc);
    }

    public function getPalette(PaletteConfig $config): ?string
    {
        $filterModel = $config->getFilterModel();

        return match (DBEquationOperator::match($filterModel->equationOperator)) {
            DBEquationOperator::IS_NULL, DBEquationOperator::IS_NOT_NULL => '{flare_simple_equation_legend},equationLeft,equationOperator',
            default => '{flare_simple_equation_legend},equationLeft,equationOperator,equationRight',
        };
    }
}