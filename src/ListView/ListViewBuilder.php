<?php

namespace HeimrichHannot\FlareBundle\ListView;

use HeimrichHannot\FlareBundle\Dto\ContentContext;
use HeimrichHannot\FlareBundle\Event\ListViewBuildEvent;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\List\ListDefinition;
use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;
use HeimrichHannot\FlareBundle\ListView\Resolver\ListViewResolverInterface;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\SortDescriptor\SortDescriptor;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ListViewBuilder
{
    private ContentContext $contentContext;
    private ListDefinition $listDefinition;
    private ListModel $listModel;
    private ?PaginatorConfig $paginatorConfig = null;
    private ?SortDescriptor $sortDescriptor = null;

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private ListViewResolverInterface         $listViewResolver,
    ) {}

    /** @api Get the content context for the list view being built. */
    public function getContentContext(): ?ContentContext
    {
        return $this->contentContext ?? null;
    }

    /* @api Set the content context for the list view being built. */
    public function setContentContext(ContentContext $contentContext): static
    {
        $this->contentContext = $contentContext;
        return $this;
    }

    /**
     * @api Get the list definition for the list view being built.
     */
    public function getListDefinition(): ?ListDefinition
    {
        return $this->listDefinition ?? null;
    }

    /**
     * @api Set the list definition for the list view being built.
     */
    public function setListDefinition(ListDefinition $listDefinition): static
    {
        $this->listDefinition = $listDefinition;
        return $this;
    }

    /**
     * @api Get the list model for the list view being built.
     * @deprecated Use {@see self::getListDefinition()} instead.
     */
    public function getListModel(): ?ListModel
    {
        return $this->listModel ?? null;
    }

    /**
     * @api Set the list model for the list view being built.
     * @deprecated Use {@see self::setListDefinition()} instead.
     */
    public function setListModel(ListModel $listModel): static
    {
        $this->listModel = $listModel;
        return $this;
    }

    public function getPaginatorConfig(): ?PaginatorConfig
    {
        return $this->paginatorConfig;
    }

    /** @api Set the paginator configuration for the list view being built. */
    public function setPaginatorConfig(?PaginatorConfig $config): static
    {
        $this->paginatorConfig = $config;
        return $this;
    }

    /** @api Get the sort descriptor for the list view being built. */
    public function getSortDescriptor(): ?SortDescriptor
    {
        return $this->sortDescriptor;
    }

    /**
     * @param SortDescriptor|null $sortDescriptor Use null to use the respective list model's default sort descriptor.
     * @api Set the sort descriptor for the list view being built.
     */
    public function setSortDescriptor(?SortDescriptor $sortDescriptor): static
    {
        $this->sortDescriptor = $sortDescriptor;
        return $this;
    }

    /** @api Get the list view resolver for the list view being built. */
    public function getListViewResolver(): ListViewResolverInterface
    {
        return $this->listViewResolver;
    }

    /** @api Set the list view resolver for the list view being built. */
    public function setListViewResolver(ListViewResolverInterface $listViewResolver): void
    {
        $this->listViewResolver = $listViewResolver;
    }

    /**
     * Builds a list view DTO.
     *
     * @throws FlareException If the builder is not configured properly.
     */
    public function build(): ListView
    {
        $event = $this->eventDispatcher->dispatch(new ListViewBuildEvent(builder: $this));

        // While the event interface should prevent builder modification,
        // we retrieve it here to maintain implementation independence.
        $builder = $event->getBuilder();

        if (!$builder->getContentContext()) {
            throw new FlareException('No content context provided.');
        }

        if (!$builder->getListDefinition()) {
            throw new FlareException('No list model provided.');
        }

        return new ListView(
            contentContext: $builder->getContentContext(),
            listDefinition: $builder->getListDefinition(),
            resolver: $builder->getListViewResolver(),
            paginatorConfig: $builder->getPaginatorConfig(),
            sortDescriptor: $builder->getSortDescriptor(),
        );
    }
}