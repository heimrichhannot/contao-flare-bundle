<?php

namespace HeimrichHannot\FlareBundle\ListView;

use HeimrichHannot\FlareBundle\Event\ListViewBuildEvent;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\List\ListContext;
use HeimrichHannot\FlareBundle\List\ListDefinition;
use HeimrichHannot\FlareBundle\ListView\Resolver\ListViewResolver;
use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;
use HeimrichHannot\FlareBundle\Projector\Projection\InteractiveProjection;
use HeimrichHannot\FlareBundle\Projector\Projection\ValidationProjection;
use HeimrichHannot\FlareBundle\SortDescriptor\SortDescriptor;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ListViewBuilder
{
    private ListContext $listContext;
    private ListDefinition $listDefinition;
    private InteractiveProjection $interactiveProjection;
    private ValidationProjection $validationProjection;
    private ?PaginatorConfig $paginatorConfig = null;
    private ?SortDescriptor $sortDescriptor = null;

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private ListViewResolver                  $listViewResolver,
    ) {}

    public function getListContext(): ListContext
    {
        return $this->listContext;
    }

    public function setListContext(ListContext $listContext): static
    {
        $this->listContext = $listContext;
        return $this;
    }

    /**
     * @api Get the list definition for the list view being built.
     */
    public function getListDefinition(): ListDefinition
    {
        return $this->listDefinition;
    }

    /**
     * @api Set the list definition for the list view being built.
     */
    public function setListDefinition(ListDefinition $listDefinition): static
    {
        $this->listDefinition = $listDefinition;
        return $this;
    }

    public function getInteractiveProjection(): InteractiveProjection
    {
        return $this->interactiveProjection;
    }

    public function setInteractiveProjection(InteractiveProjection $interactiveProjection): static
    {
        $this->interactiveProjection = $interactiveProjection;
        return $this;
    }

    public function getValidationProjection(): ValidationProjection
    {
        return $this->validationProjection;
    }

    public function setValidationProjection(ValidationProjection $validationProjection): static
    {
        $this->validationProjection = $validationProjection;
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
    public function getListViewResolver(): ListViewResolver
    {
        return $this->listViewResolver;
    }

    /** @api Set the list view resolver for the list view being built. */
    public function setListViewResolver(ListViewResolver $listViewResolver): void
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
        /** @var ListViewBuilder $builder */
        $builder = $event->getBuilder();

        if (!isset($builder->listContext)) {
            throw new FlareException('No content context provided.');
        }

        if (!isset($builder->listDefinition)) {
            throw new FlareException('No list model provided.');
        }

        if (!isset($builder->interactiveProjection)) {
            throw new FlareException('No interactive projection provided.');
        }

        if (!isset($builder->validationProjection)) {
            throw new FlareException('No validation projection provided.');
        }

        return new ListView(
            listContext: $builder->getListContext(),
            listDefinition: $builder->getListDefinition(),
            resolver: $builder->getListViewResolver(),
            interactiveProjection: $builder->getInteractiveProjection(),
            validationProjection: $builder->getValidationProjection(),
        );
    }
}