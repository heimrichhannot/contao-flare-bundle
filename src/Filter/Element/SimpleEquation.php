<?php

namespace HeimrichHannot\FlareBundle\Filter\Element;

use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\PaletteContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterCallback;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Util\DBEquationOperator;
use HeimrichHannot\FlareBundle\Util\DcaHelper;

#[AsFilterElement(alias: SimpleEquation::TYPE)]
class SimpleEquation extends AbstractFilterElement implements PaletteContract
{
    public const TYPE = 'flare_equation_simple';

    public function __invoke(FilterContext $context, FilterQueryBuilder $qb): void
    {
        $filterModel = $context->getFilterModel();
    }

    #[AsFilterCallback(self::TYPE, 'fields.equationLeft.options')]
    public function onLoad_preselect2(ListModel $listModel): array
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