<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Manager;

use Contao\Controller;
use HeimrichHannot\FlareBundle\Contract\Config\PresetFiltersConfig;
use HeimrichHannot\FlareBundle\Contract\ListType\PresetFiltersContract;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterContextCollection;
use HeimrichHannot\FlareBundle\Filter\FilterElementRegistry;
use HeimrichHannot\FlareBundle\List\ListTypeRegistry;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;

/**
 * Class FilterContextManager
 *
 * Manages the filter context, including collecting filter contexts.
 */
readonly class FilterContextManager
{
    public function __construct(
        private FilterElementRegistry $filterElementRegistry,
        private ListTypeRegistry $listTypeRegistry,
    ) {}

    /**
     * Collects filter contexts for a given list model.
     */
    public function collect(ListModel $listModel): ?FilterContextCollection
    {
        if (!$listModel->id || !$table = $listModel->dc) {
            return null;
        }

        if (!$listTypeConfig = $this->listTypeRegistry->get($listModel->type)) {
            return null;
        }

        if (!$listType = $listTypeConfig->getService()) {
            return null;
        }

        $filterModels = FilterModel::findByPid($listModel->id, published: true);

        if (!$filterModels->count()) {
            return null;
        }

        Controller::loadDataContainer($table);

        $filters = FilterContextCollection::create($listModel);

        $addedFilters = [];

        foreach ($filterModels as $filterModel)
        {
            if (!$filterModel->published) {
                continue;
            }

            $filterElementAlias = $filterModel->type;

            if (!$config = $this->filterElementRegistry->get($filterElementAlias)) {
                continue;
            }

            $filters->add(new FilterContext($listModel, $filterModel, $config, $filterElementAlias, $table));

            $addedFilters[] = $filterElementAlias;
        }

        if ($listType instanceof PresetFiltersContract)
        {
            $addedFilters = \array_unique($addedFilters);

            $presetConfig = new PresetFiltersConfig(
                listModel: $listModel,
                manualFilterAliases: $addedFilters,
            );

            $listType->getPresetFilters($presetConfig);

            // todo: continue work on this mechanic
        }

        return $filters;
    }
}