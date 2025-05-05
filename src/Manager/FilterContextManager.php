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
use HeimrichHannot\FlareBundle\List\Type\AbstractListType;
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

        Controller::loadDataContainer($table);

        $filterModels = FilterModel::findByPid($listModel->id, published: true) ?? [];
        $collection = FilterContextCollection::create($listModel);

        $addedFilters = [];

        foreach ($filterModels as $filterModel)
            // Collect filters defined in the backend
        {
            if (!$filterModel->published) {
                continue;
            }

            $filterElementAlias = $filterModel->type;

            if (!$config = $this->filterElementRegistry->get($filterElementAlias)) {
                continue;
            }

            $collection->add(new FilterContext($listModel, $filterModel, $config, $filterElementAlias, $table));

            $addedFilters[] = $filterElementAlias;
        }

        // Add filters defined by the filter element type
        $this->addPresetFilters($collection, $listModel, $listType, $addedFilters);

        return $collection;
    }

    private function addPresetFilters(
        FilterContextCollection $collection,
        ListModel               $listModel,
        object                  $listType,
        array                   $manualFilters,
    ): void {
        if (!$listType instanceof PresetFiltersContract) {
            return;
        }

        $manualFilters = \array_unique($manualFilters);

        $presetConfig = new PresetFiltersConfig(
            listModel: $listModel,
            manualFilterAliases: $manualFilters,
        );

        $listType->getPresetFilters($presetConfig);

        $filterDefinitions = $presetConfig->getFilterDefinitions();

        foreach ($filterDefinitions as $arrDefinition)
        {
            ['definition' => $definition, 'final' => $final] = $arrDefinition;

            if (!$final && \in_array($definition->getAlias(), $manualFilters, true))
                // skip if filter is not final and already added
            {
                continue;
            }

            if (!$config = $this->filterElementRegistry->get($definition->getAlias())) {
                continue;
            }

            $filterModel = new FilterModel();
            $filterModel->setRow($definition->getRow());

            $collection->add(new FilterContext($listModel, $filterModel, $config, $definition->getAlias(), $listModel->dc));
        }

        // todo: overhaul this mechanic
    }
}