<?php

namespace HeimrichHannot\FlareBundle\ListView\Builder;

use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;
use HeimrichHannot\FlareBundle\ListView\ListViewDto;
use HeimrichHannot\FlareBundle\ListView\Resolver\ListViewResolverInterface;
use HeimrichHannot\FlareBundle\Model\ListModel;

class ListViewBuilder
{
    private ?string $formName = null;
    private ListModel $listModel;
    private PaginatorConfig $paginatorConfig;

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

    public function setPaginatorConfig(PaginatorConfig $config): static
    {
        $this->paginatorConfig = $config;
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
            formName: $this->formName,
        );
    }
}