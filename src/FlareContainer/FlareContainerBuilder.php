<?php

namespace HeimrichHannot\FlareBundle\FlareContainer;

use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Model\ListModel;

class FlareContainerBuilder
{
    private ListModel $listModel;
    private ?string $formName = null;

    public function __construct(
        private readonly FlareContainerStrategies $containerStrategies,
    ) {}

    public function setListModel(ListModel $listModel): static
    {
        $this->listModel = $listModel;
        return $this;
    }

    public function setFormName(?string $formName): static
    {
        $this->formName = $formName;
        return $this;
    }

    /**
     * @throws FlareException
     */
    public function build(): FlareContainer
    {
        if (!isset($this->listModel)) {
            throw new FlareException('No list model provided.');
        }

        return new FlareContainer($this->listModel, $this->containerStrategies, $this->formName);
    }
}