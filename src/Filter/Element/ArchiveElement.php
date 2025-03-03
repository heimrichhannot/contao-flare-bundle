<?php

namespace HeimrichHannot\FlareBundle\Filter\Element;

use Doctrine\DBAL\Query\QueryBuilder;
use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\PaletteContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;

#[AsFilterElement(ArchiveElement::TYPE)]
class ArchiveElement extends AbstractFilterElement implements PaletteContract
{
    const TYPE = 'flare_archive';

    public function __invoke(
        QueryBuilder $queryBuilder,
        FilterModel  $filterModel,
        ListModel    $listModel,
        string       $table
    ): void {}

    public function getPalette(PaletteConfig $config): ?string
    {
        return '';
    }
}