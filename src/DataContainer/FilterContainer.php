<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\DataContainer;

use Contao\DataContainer;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;

class FilterContainer
{
    public const TABLE_NAME = 'tl_flare_filter';

    /**
     * @param DataContainer|null $dc
     * @param bool               $ignoreType
     * @return array{FilterModel, ListModel}|array{null, null}
     * @mago-expect lint:no-empty-catch-clause It's fine if the models are not found.
     */
    public function getModelsFromDataContainer(?DataContainer $dc, bool $ignoreType = false): array
    {
        try
        {
            if (($id = $dc?->id)
                && ($filterModel = FilterModel::findByPk($id))
                && ($ignoreType || $filterModel->type)
                && ($listModel = $filterModel->getRelated('pid')))
            {
                if (!$listModel instanceof ListModel) {
                    return [null, null];
                }

                return [$filterModel, $listModel];
            }
        }
        catch (\Throwable) {}

        return [null, null];
    }
}
