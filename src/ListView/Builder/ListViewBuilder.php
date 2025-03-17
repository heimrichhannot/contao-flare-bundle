<?php

namespace HeimrichHannot\FlareBundle\ListView\Builder;

use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\ListView\ListViewDto;
use HeimrichHannot\FlareBundle\ListView\Resolver\ListViewResolverInterface;
use HeimrichHannot\FlareBundle\Model\ListModel;

class ListViewBuilder
{
    private ListModel $listModel;
    private ?string $formName = null;

    public function __construct(
        private readonly ListViewResolverInterface $containerStrategies,
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
    public function build(): ListViewDto
    {
        if (!isset($this->listModel)) {
            throw new FlareException('No list model provided.');
        }

        return new ListViewDto($this->listModel, $this->containerStrategies, $this->formName);
    }
}