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
        $this->applyPresetFilters($listType, $addedFilters, $listModel, $collection, $table);

        return $collection;
    }

    /**
     * @noinspection PhpDocSignatureInspection
     * @param object|AbstractListType      $listType
     * @param array                        $addedFilters
     * @param ListModel                    $listModel
     * @param FilterContextCollection|null $filters
     * @param string                       $table
     * @return void
     */
    private function applyPresetFilters(
        object $listType,
        array $addedFilters,
        ListModel $listModel,
        ?FilterContextCollection $filters,
        string $table
    ): void {
        if (!$listType instanceof PresetFiltersContract) {
            return;
        }

        $addedFilters = \array_unique($addedFilters);

        $presetConfig = new PresetFiltersConfig(
            listModel: $listModel,
            manualFilterAliases: $addedFilters,
        );

        $listType->getPresetFilters($presetConfig);

        $filterDefinitions = $presetConfig->getFilterDefinitions();

        foreach ($filterDefinitions as $arrDefinition)
        {
            ['definition' => $definition, 'final' => $final] = $arrDefinition;

            if (!$final && \in_array($definition->getType(), $addedFilters, true))
                // skip if filter is not final and already added
            {
                continue;
            }

            if (!$config = $this->filterElementRegistry->get($definition->getType())) {
                continue;
            }

            $filterModel = new FilterModel();
            $filterModel->setRow($definition->getRow());

            $filters->add(new FilterContext($listModel, $filterModel, $config, $definition->getType(), $table));
        }

        // todo: overhaul this mechanic
    }
}