<?php

namespace HeimrichHannot\FlareBundle\Filter\Element;

use Contao\Message;
use Controller;
use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\PaletteContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder;

#[AsFilterElement(alias: ArchiveElement::TYPE, palette: '{filter_legend},fieldPid')]
class ArchiveElement extends AbstractFilterElement implements PaletteContract
{
    const TYPE = 'flare_archive';

    public function __invoke(FilterQueryBuilder $qb, FilterContext $context): void
    {

    }

    public function getPalette(PaletteConfig $config): ?string
    {
        $listModel = $config->getListModel();
        $filterModel = $config->getFilterModel();

        if (!$listModel || !$filterModel) {
            Message::addError('List model or filter model not found.');
            return '';
        }

        if (!$entityTable = $listModel->dc) {
            Message::addError('Please define a data container for the list model ' . $listModel->getTable());
            return '';
        }

        Controller::loadDataContainer($entityTable);

        if (!$entityDca = $GLOBALS['TL_DCA'][$entityTable] ?? null) {
            Message::addError('Data container array not found for ' . $entityTable);
            return null;
        }

        $dynamicPtable = $entityDca['config']['dynamicPtable'] ?? null;
        $ptable = $entityDca['config']['ptable'] ?? null;

        if (!$dynamicPtable && $ptable === null) {
            $msg = \sprintf('Parent table cannot be inferred automatically on "%s"', $entityTable);
            $method = $filterModel->whichPtable === 'auto' ? 'addError' : 'addInfo';
            Message::{$method}($msg);
        }

        $palette = '{filter_legend},fieldPid,whichPtable';

        if ($dynamicPtable) {
            $palette .= ',fieldPtable';
        }

        return $palette;
    }
}