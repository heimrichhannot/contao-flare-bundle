<?php

namespace HeimrichHannot\FlareBundle\List;

use HeimrichHannot\FlareBundle\Filter\FilterDefinition;
use HeimrichHannot\FlareBundle\Model\ListModel;

class PresetFiltersConfig
{
    /** @var array<array{
     *     definition: FilterDefinition,
     *     final: bool
     * }> $filterDefinitions
     */
    private array $filterDefinitions = [];

    /**
     * @param ListModel     $listModel
     * @param array<string> $manualFilterTypes
     */
    public function __construct(
        private readonly ListModel $listModel,
        private readonly array     $manualFilterTypes,
    ) {}

    public function getListModel(): ListModel
    {
        return $this->listModel;
    }

    public function getManualFilterTypes(): array
    {
        return $this->manualFilterTypes;
    }

    public function add(FilterDefinition $filterDefinition, bool $replaceable = false): static
    {
        $this->filterDefinitions[] = [
            'definition' => $filterDefinition,
            'replaceable' => $replaceable,
        ];

        return $this;
    }

    /**
     * @return array<array{
     *     definition: FilterDefinition,
     *     final: bool
     * }>
     */
    public function getFilterDefinitions(): array
    {
        return $this->filterDefinitions;
    }
}