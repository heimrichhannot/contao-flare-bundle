<?php

namespace HeimrichHannot\FlareBundle\Filter\Element;

use Doctrine\DBAL\Query\QueryBuilder;
use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\PaletteContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;

#[AsFilterElement(
    alias: PublishedElement::TYPE,
    palette: '{filter_legend},useStart,useStop'
)]
class PublishedElement extends AbstractFilterElement implements PaletteContract
{
    const TYPE = 'flare_published';

    public function __invoke(
        QueryBuilder $queryBuilder,
        FilterModel  $filterModel,
        ListModel    $listModel,
        string       $table
    ): void {
        $queryBuilder->andWhere("e.published = 1");

        if ($useStart = $filterModel->useStart ?? true)
        {
            $startField = $filterModel->field_start ?: "$table.start";
            $queryBuilder->andWhere("$startField = \"\" OR $startField = 0 OR $startField <= ?");
        }

        if ($useStop = $filterModel->useStop ?? true)
        {
            $stopField = $filterModel->field_stop ?: "$table.stop";
            $queryBuilder->andWhere("$stopField = \"\" OR $stopField = 0 OR $stopField >= ?");
        }

        $numParams = (int) $useStart + (int) $useStop;

        if ($numParams > 0) {
            $queryBuilder->setParameter(0, time());
        }

        if ($numParams > 1) {
            $queryBuilder->setParameter(1, time());
        }
    }

    public function getPalette(PaletteConfig $config): ?string
    {
        return '';
    }
}