<?php

namespace HeimrichHannot\FlareBundle\Contract\Config;

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
     * @param array<string> $manualFilterAliases
     */
    public function __construct(
        private readonly ListModel $listModel,
        private readonly array     $manualFilterAliases,
    ) {}

    public function getListModel(): ListModel
    {
        return $this->listModel;
    }

    public function getManualFilterAliases(): array
    {
        return $this->manualFilterAliases;
    }

    public function add(FilterDefinition $filterDefinition, bool $final = false): static
    {
        $this->filterDefinitions[] = [
            'definition' => $filterDefinition,
            'final'      => $final,
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