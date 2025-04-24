<?php

namespace HeimrichHannot\FlareBundle\Contract\Config;

use HeimrichHannot\FlareBundle\Model\ListModel;

class PresetFiltersConfig
{
    /** @var array{object} $filterDefinitions
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
        $this->filterDefinitions[] = new class($filterDefinition, $final)
        {
            public function __construct(
                private readonly FilterDefinition $filterDefinition,
                private readonly bool             $final,
            ) {}

            public function getFilterDefinition(): FilterDefinition
            {
                return $this->filterDefinition;
            }

            public function isFinal(): bool
            {
                return $this->final;
            }
        };

        return $this;
    }

    public function getFilterDefinitions(): array
    {
        return $this->filterDefinitions;
    }
}