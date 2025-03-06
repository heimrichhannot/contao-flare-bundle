<?php

namespace HeimrichHannot\FlareBundle\Filter\Element;

use Contao\Message;
use Controller;
use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\PaletteContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Util\DcaHelper;

#[AsFilterElement(alias: BelongsToRelationElement::TYPE, palette: '{filter_legend},fieldPid')]
class BelongsToRelationElement extends AbstractFilterElement implements PaletteContract
{
    const TYPE = 'flare_relation_belongsTo';

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
        $inferredPtable = null;

        $palette = '{filter_legend},fieldPid,whichPtable';

        if (\is_string($ptable))
        {
            $inferredPtable = $ptable;
            Message::addInfo(\sprintf('Parent table of "%s" inferred automatically as "%s"', $entityTable, $ptable));
        }
        else
        {
            if ($filterModel->whichPtable === 'auto')
            {
                $filterModel->whichPtable = 'dynamic';
                $filterModel->save();
            }

            $GLOBALS['TL_DCA'][FilterModel::getTable()]['fields']['whichPtable']['options'] = ['dynamic', 'static'];
            $GLOBALS['TL_DCA'][FilterModel::getTable()]['fields']['whichPtable']['default'] = ['dynamic'];

            Message::addInfo($dynamicPtable
                ? \sprintf('Parent table of "%s" is inferred dynamically', $entityTable)
                : \sprintf('Parent table cannot be inferred automatically on "%s"', $entityTable)
            );
        }

        $inferredPtable = match ($filterModel->whichPtable) {
            'dynamic' => null,
            'static' => $filterModel->tablePtable ?? null,
            default => $inferredPtable,
        };

        if ($filterModel->whichPtable === 'dynamic')
        {
            $palette .= ';{archive_legend},groupWhitelistParents';
        }
        elseif ($inferredPtable)
        {
            $options = DcaHelper::getArchiveOptions($inferredPtable);
            $GLOBALS['TL_DCA'][FilterModel::getTable()]['fields']['whitelistParents']['options'] = $options;
            $palette .= ';{archive_legend},whitelistParents';
        }

        return $palette;
    }
}