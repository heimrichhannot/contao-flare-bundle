<?php

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Dto\ContentContext;
use HeimrichHannot\FlareBundle\Filter\FilterContextCollection;
use HeimrichHannot\FlareBundle\Filter\FilterDefinitionCollection;
use HeimrichHannot\FlareBundle\List\ListDefinition;
use HeimrichHannot\FlareBundle\List\ListQueryBuilder;
use HeimrichHannot\FlareBundle\ListItemProvider\ListItemProviderInterface;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;
use HeimrichHannot\FlareBundle\SortDescriptor\SortDescriptor;
use Symfony\Component\Form\FormInterface;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractFetchEvent extends Event
{
    public function __construct(
        private readonly ListDefinition    $listDefinition,
        private ListItemProviderInterface  $itemProvider,
        private ListQueryBuilder           $listQueryBuilder,
        private FilterDefinitionCollection $filters,
        private readonly ?FormInterface    $form = null,
        private readonly ?PaginatorConfig  $paginatorConfig = null,
        private readonly ?SortDescriptor   $sortDescriptor = null,
    ) {}

    public function getListDefinition(): ListDefinition
    {
        return $this->listDefinition;
    }

    public function getPaginatorConfig(): PaginatorConfig
    {
        return $this->paginatorConfig;
    }

    public function getForm(): FormInterface
    {
        return $this->form;
    }

    public function getItemProvider(): ListItemProviderInterface
    {
        return $this->itemProvider;
    }

    public function setItemProvider(ListItemProviderInterface $itemProvider): void
    {
        $this->itemProvider = $itemProvider;
    }

    public function getListQueryBuilder(): ListQueryBuilder
    {
        return $this->listQueryBuilder;
    }

    public function setListQueryBuilder(ListQueryBuilder $listQueryBuilder): void
    {
        $this->listQueryBuilder = $listQueryBuilder;
    }

    public function getFilters(): FilterDefinitionCollection
    {
        return $this->filters;
    }

    public function setFilters(FilterDefinitionCollection $filters): void
    {
        $this->filters = $filters;
    }

    public function getSortDescriptor(): ?SortDescriptor
    {
        return $this->sortDescriptor;
    }
}