<?php

namespace HeimrichHannot\FlareBundle\Projector;

use HeimrichHannot\FlareBundle\Event\FetchListEntriesEvent;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Factory\PaginatorBuilderFactory;
use HeimrichHannot\FlareBundle\List\ListContext;
use HeimrichHannot\FlareBundle\List\ListDefinition;
use HeimrichHannot\FlareBundle\Manager\FilterFormManager;
use HeimrichHannot\FlareBundle\Manager\ListItemProviderManager;
use HeimrichHannot\FlareBundle\Manager\ListQueryManager;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use HeimrichHannot\FlareBundle\Projector\Projection\AggregationProjection;
use HeimrichHannot\FlareBundle\Projector\Projection\InteractiveProjection;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @implements ProjectorInterface<InteractiveProjection>
 */
class InteractiveProjector extends AbstractProjector
{
    public static function getContext(): string
    {
        return ListContext::INTERACTIVE;
    }

    public static function getProjectionClass(): string
    {
        return InteractiveProjection::class;
    }

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly FilterFormManager        $filterFormManager,
        private readonly RequestStack             $requestStack,
        private readonly ListItemProviderManager  $itemProvider,
        private readonly ListQueryManager         $listQueryManager,
        private readonly PaginatorBuilderFactory  $paginatorBuilderFactory,
        private readonly Projectors               $projectors,
    ) {}

    protected function execute(ListContext $listContext, ListDefinition $listDefinition): InteractiveProjection
    {
        $form = $this->getForm($listContext, $listDefinition);
        $totalItems = $this->getAggregationProjection($listContext, $listDefinition)->getCount();
        $paginator = $this->getPaginator($form, $listContext, $totalItems);

        // Override list context to include the proper paginator config
        $listContext = $listContext->with(paginatorConfig: $paginator);

        $fetchEntries = function () use ($form, $listContext, $listDefinition): array {
            return $this->fetchEntries($form, $listContext, $listDefinition);
        };

        return new InteractiveProjection(
            fetchEntries: $fetchEntries,
            form: $form,
            paginator: $paginator,
            table: $listDefinition->dc,
            totalItems: $totalItems,
        );
    }

    public function getForm(ListContext $listContext, ListDefinition $listDefinition): FormInterface
    {
        if (!$request = $this->requestStack->getCurrentRequest()) {
            throw new FilterException('Request not available', source: __METHOD__);
        }

        $form = $this->filterFormManager->buildForm($listContext, $listDefinition);
        $form->handleRequest($request);

        $this->filterFormManager->hydrateForm($form, $listDefinition);
        // $this->filterFormManager->hydrateFilterElements($form, $listDefinition);

        return $form;
    }

    private function getAggregationProjection(
        ListContext    $listContext,
        ListDefinition $listDefinition
    ): AggregationProjection {
        $projection = $this->projectors->project(
            projectionClass: AggregationProjection::class,
            listContext: $listContext,
            listDefinition: $listDefinition
        );

        if (!$projection instanceof AggregationProjection) {
            throw new FlareException('Aggregation projection not available', source: __METHOD__);
        }

        return $projection;
    }

    public function getPaginator(FormInterface $form, ListContext $listContext, int $totalItems): Paginator
    {
        if ($form->isSubmitted() && !$form->isValid()) {
            return $this->paginatorBuilderFactory->create()->buildEmpty();
        }

        return $this->paginatorBuilderFactory
            ->create()
            ->fromConfig($listContext->paginatorConfig)
            ->queryPrefix($form->getName())
            ->handleRequest()
            ->totalItems($totalItems)
            ->build();
    }

    public function fetchEntries(FormInterface $form, ListContext $listContext, ListDefinition $listDefinition)
    {
        try
        {
            if ($form->isSubmitted() && !$form->isValid()) {
                return [];
            }

            $itemProvider = $this->itemProvider->ofList($listDefinition);
            $listQueryBuilder = $this->listQueryManager->prepare($listDefinition);

            $event = $this->eventDispatcher->dispatch(
                new FetchListEntriesEvent(
                    listContext: $listContext,
                    listDefinition: $listDefinition,
                    itemProvider: $itemProvider,
                    listQueryBuilder: $listQueryBuilder,
                )
            );

            $itemProvider = $event->getItemProvider();
            $listQueryBuilder = $event->getListQueryBuilder();

            return $itemProvider->fetchEntries(
                listQueryBuilder: $listQueryBuilder,
                listDefinition: $listDefinition,
                listContext: $listContext,
            );
        }
        catch (FlareException $e)
        {
            throw $e;
        }
        catch (\Throwable $e)
        {
            throw new FlareException($e->getMessage(), $e->getCode(), $e);
        }
    }
}