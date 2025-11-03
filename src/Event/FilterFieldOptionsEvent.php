<?php

namespace HeimrichHannot\FlareBundle\Event;

use Contao\DataContainer;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;

class FilterFieldOptionsEvent
{
    private array $options = [];

    public function __construct(
        public readonly DataContainer $dataContainer,
        public readonly FilterModel   $filterModel,
        public readonly ListModel     $listModel,
        public readonly array         $tables,
        public readonly string        $targetTable,
    ) {}

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setOptions(array $options): void
    {
        $this->options = $options;
    }
}