<?php

namespace HeimrichHannot\FlareBundle\Event;

use Contao\DataContainer;
use HeimrichHannot\FlareBundle\Model\ListModel;

class ListFieldOptionsEvent
{
    private array $options = [];

    public function __construct(
        private readonly ?DataContainer $dataContainer,
        private readonly ListModel      $listModel,
    ) {}

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    public function getDataContainer(): ?DataContainer
    {
        return $this->dataContainer;
    }

    public function getListModel(): ListModel
    {
        return $this->listModel;
    }
}