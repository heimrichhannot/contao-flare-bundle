<?php

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Dto\ContentContext;
use HeimrichHannot\FlareBundle\Enum\FetchSubject;
use HeimrichHannot\FlareBundle\Filter\FilterContextCollection;
use HeimrichHannot\FlareBundle\List\ListQueryBuilder;
use HeimrichHannot\FlareBundle\ListItemProvider\ListItemProviderInterface;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;
use HeimrichHannot\FlareBundle\SortDescriptor\SortDescriptor;
use Symfony\Component\Form\FormInterface;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractFetchEvent extends Event
{
    abstract public function subject(): FetchSubject;

    abstract public function getEventName(): string;

    public function __construct(
        private readonly ListModel        $listModel,
        private readonly ContentContext   $contentContext,
        private ListItemProviderInterface $itemProvider,
        private ListQueryBuilder          $listQueryBuilder,
        private FilterContextCollection   $filters,
        private readonly ?FormInterface   $form = null,
        private readonly ?PaginatorConfig $paginatorConfig = null,
        private readonly ?SortDescriptor  $sortDescriptor = null,
        private readonly string|int|null  $autoItem = null,
    ) {}

    public function getListModel(): ListModel
    {
        return $this->listModel;
    }

    public function getContentContext(): ContentContext
    {
        return $this->contentContext;
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

    public function getFilters(): FilterContextCollection
    {
        return $this->filters;
    }

    public function setFilters(FilterContextCollection $filters): void
    {
        $this->filters = $filters;
    }

    public function getSortDescriptor(): ?SortDescriptor
    {
        return $this->sortDescriptor;
    }

    public function getAutoItem(): int|string|null
    {
        return $this->autoItem;
    }
}