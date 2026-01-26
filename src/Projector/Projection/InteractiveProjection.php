<?php

namespace HeimrichHannot\FlareBundle\Projector\Projection;

use HeimrichHannot\FlareBundle\Event\FetchCountEvent;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Factory\PaginatorBuilderFactory;
use HeimrichHannot\FlareBundle\List\ListContext;
use HeimrichHannot\FlareBundle\List\ListDefinition;
use HeimrichHannot\FlareBundle\Manager\FilterFormManager;
use HeimrichHannot\FlareBundle\Manager\ListItemProviderManager;
use HeimrichHannot\FlareBundle\Manager\ListQueryManager;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class InteractiveProjection implements ProjectionInterface
{
    private FormInterface $form;
    private Paginator $paginator;

    public function __construct(
        private readonly AggregationProjection    $aggregationProjection,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly FilterFormManager        $formManager,
        private readonly ListContext              $listContext,
        private readonly ListDefinition           $listDefinition,
        private readonly ListItemProviderManager  $itemProvider,
        private readonly ListQueryManager         $listQueryManager,
        private readonly PaginatorBuilderFactory  $paginatorBuilderFactory,
        private readonly Request                  $request,
    ) {}

    public function getForm(): FormInterface
    {
        if (isset($this->form)) {
            return $this->form;
        }

        $form = $this->formManager->buildForm($this->listContext, $this->listDefinition);
        $form->handleRequest($this->request);

        $this->formManager->hydrateForm($form, $this->listDefinition);
        // $this->formManager->hydrateFilterElements($form, $listDefinition);

        return $this->form = $form;
    }

    public function getPaginator(): Paginator
    {
        if (isset($this->paginator)) {
            return $this->paginator;
        }

        $form = $this->getForm();

        if ($form->isSubmitted() && !$form->isValid()) {
            return $this->paginatorBuilderFactory->create()->buildEmpty();
        }

        return $this->paginator = $this->paginatorBuilderFactory
            ->create()
            ->fromConfig($this->listContext->paginatorConfig)
            ->queryPrefix($form->getName())
            ->handleRequest()
            ->totalItems($this->getCount())
            ->build();
    }

    public function getCount(): int
    {
        return $this->aggregationProjection->getCount();
    }

    public function getEntries(): array
    {
        // todo
    }
}