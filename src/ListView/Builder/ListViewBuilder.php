<?php

namespace HeimrichHannot\FlareBundle\ListView\Builder;

use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;
use HeimrichHannot\FlareBundle\ListView\ListViewDto;
use HeimrichHannot\FlareBundle\ListView\Resolver\ListViewResolverInterface;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\SortDescriptor\SortDescriptor;

class ListViewBuilder
{
    private ?string $formName = null;
    private ListModel $listModel;
    private ?PaginatorConfig $paginatorConfig = null;
    private ?SortDescriptor $sortDescriptor = null;

    public function __construct(
        private readonly ListViewResolverInterface $resolver,
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

    public function setPaginatorConfig(?PaginatorConfig $config): static
    {
        $this->paginatorConfig = $config;
        return $this;
    }

    /**
     * @param SortDescriptor|null $sortDescriptor Use null to use the respective list model's default sort descriptor.
     */
    public function setSortDescriptor(?SortDescriptor $sortDescriptor): static
    {
        $this->sortDescriptor = $sortDescriptor;
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

        return new ListViewDto(
            listModel: $this->listModel,
            resolver: $this->resolver,
            paginatorConfig: $this->paginatorConfig,
            sortDescriptor: $this->sortDescriptor,
            formName: $this->formName,
        );
    }
}