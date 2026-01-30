<?php

namespace HeimrichHannot\FlareBundle\ListView;

use HeimrichHannot\FlareBundle\Engine\Context\InteractiveContext;
use HeimrichHannot\FlareBundle\Engine\View\InteractiveView;
use HeimrichHannot\FlareBundle\Event\ListViewBuildEvent;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;
use HeimrichHannot\FlareBundle\SortDescriptor\SortDescriptor;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @todo(@ericges): Remove in 0.1.0
 * @deprecated Use {@see InteractiveView} instead.
 */
class ListViewBuilder
{
    private InteractiveContext $interactiveConfig;
    private ListSpecification $listDefinition;
    private InteractiveView $interactiveView;
    private ?PaginatorConfig $paginatorConfig = null;
    private ?SortDescriptor $sortDescriptor = null;

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function getInteractiveConfig(): InteractiveContext
    {
        return $this->interactiveConfig;
    }

    public function setInteractiveConfig(InteractiveContext $interactiveConfig): static
    {
        $this->interactiveConfig = $interactiveConfig;
        return $this;
    }

    /**
     * @api Get the list definition for the list view being built.
     */
    public function getListDefinition(): ListSpecification
    {
        return $this->listDefinition;
    }

    /**
     * @api Set the list definition for the list view being built.
     */
    public function setListDefinition(ListSpecification $listDefinition): static
    {
        $this->listDefinition = $listDefinition;
        return $this;
    }

    public function getInteractiveView(): InteractiveView
    {
        return $this->interactiveView;
    }

    public function setInteractiveView(InteractiveView $interactiveView): static
    {
        $this->interactiveView = $interactiveView;
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

        if (!isset($builder->interactiveConfig)) {
            throw new FlareException('No content context provided.');
        }

        if (!isset($builder->listDefinition)) {
            throw new FlareException('No list model provided.');
        }

        if (!isset($builder->interactiveView)) {
            throw new FlareException('No interactive projection provided.');
        }

        return new ListView(
            interactiveConfig: $builder->getInteractiveConfig(),
            listSpecification: $builder->getListDefinition(),
            interactiveProjection: $builder->getInteractiveView(),
        );
    }
}