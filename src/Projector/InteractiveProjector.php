<?php

namespace HeimrichHannot\FlareBundle\Projector;

use HeimrichHannot\FlareBundle\Context\ContextConfigInterface;
use HeimrichHannot\FlareBundle\Context\Factory\AggregationConfigFactory;
use HeimrichHannot\FlareBundle\Context\InteractiveConfig;
use HeimrichHannot\FlareBundle\Context\Interface\PaginatedContextInterface;
use HeimrichHannot\FlareBundle\Event\FetchListEntriesEvent;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Factory\PaginatorBuilderFactory;
use HeimrichHannot\FlareBundle\Generator\ReaderPageUrlGenerator;
use HeimrichHannot\FlareBundle\Manager\FilterFormManager;
use HeimrichHannot\FlareBundle\Manager\ListItemProviderManager;
use HeimrichHannot\FlareBundle\Manager\ListQueryManager;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use HeimrichHannot\FlareBundle\Projector\Registry\ProjectorRegistry;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use HeimrichHannot\FlareBundle\View\AggregationView;
use HeimrichHannot\FlareBundle\View\InteractiveView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @implements ProjectorInterface<InteractiveView>
 */
class InteractiveProjector extends AbstractProjector
{
    public function __construct(
        private readonly AggregationConfigFactory $aggregationConfigFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly FilterFormManager        $filterFormManager,
        private readonly ListItemProviderManager  $listItemProviderManager,
        private readonly ListQueryManager         $listQueryManager,
        private readonly PaginatorBuilderFactory  $paginatorBuilderFactory,
        private readonly ProjectorRegistry        $projectorRegistry,
        private readonly RequestStack             $requestStack,
        private readonly ReaderPageUrlGenerator   $readerPageUrlGenerator,
    ) {}

    public function supports(ContextConfigInterface $config): bool
    {
        return $config instanceof InteractiveConfig;
    }

    public function project(ListSpecification $spec, ContextConfigInterface $config): InteractiveView
    {
        \assert($config instanceof InteractiveConfig);

        $form = $this->getForm($spec, $config);
        $totalItems = $this->getAggregationProjection($spec, $config)->getCount();
        $paginator = $this->getPaginator($form, $config, $totalItems);

        // Override list context to include the proper paginator config
        $config = $config->with(paginatorConfig: $paginator);

        $fetchEntries = function () use ($spec, $config, $form): array {
            return $this->fetchEntries($spec, $config, $form);
        };

        $readerUrlGenerator = $this->readerPageUrlGenerator->createCallable($config);

        return new InteractiveView(
            fetchEntries: $fetchEntries,
            form: $form,
            paginator: $paginator,
            readerUrlGenerator: $readerUrlGenerator,
            table: $spec->dc,
            totalItems: $totalItems,
        );
    }

    public function getForm(ListSpecification $spec, InteractiveConfig $config): FormInterface
    {
        if (!$request = $this->requestStack->getCurrentRequest()) {
            throw new FilterException('Request not available', source: __METHOD__);
        }

        $form = $this->filterFormManager->buildForm($spec, $config);
        $form->handleRequest($request);

        $this->filterFormManager->hydrateForm($form, $spec);
        // $this->filterFormManager->hydrateFilterElements($form, $listDefinition);

        return $form;
    }

    private function getAggregationProjection(
        ListSpecification $spec,
        InteractiveConfig $interactiveConfig
    ): AggregationView {
        $aggregationConfig = $this->aggregationConfigFactory->createFromConfig($interactiveConfig);
        $projector = $this->projectorRegistry->getProjectorFor($aggregationConfig);
        $projection = $projector->project($spec, $aggregationConfig);

        \assert($projection instanceof AggregationView);

        return $projection;
    }

    public function getPaginator(FormInterface $form, PaginatedContextInterface $context, int $totalItems): Paginator
    {
        if ($form->isSubmitted() && !$form->isValid()) {
            return $this->paginatorBuilderFactory->create()->buildEmpty();
        }

        return $this->paginatorBuilderFactory
            ->create()
            ->fromConfig($context->getPaginatorConfig())
            ->queryPrefix($form->getName())
            ->handleRequest()
            ->totalItems($totalItems)
            ->build();
    }

    public function fetchEntries(ListSpecification $spec, InteractiveConfig $config, FormInterface $form): array
    {
        try
        {
            if ($form->isSubmitted() && !$form->isValid()) {
                return [];
            }

            $itemProvider = $this->listItemProviderManager->ofList($spec);
            $listQueryBuilder = $this->listQueryManager->prepare($spec);

            $event = $this->eventDispatcher->dispatch(
                new FetchListEntriesEvent(
                    contextConfig: $config,
                    listSpecification: $spec,
                    itemProvider: $itemProvider,
                    listQueryBuilder: $listQueryBuilder,
                )
            );

            $itemProvider = $event->getItemProvider();
            $listQueryBuilder = $event->getListQueryBuilder();

            return $itemProvider->fetchEntries(
                listQueryBuilder: $listQueryBuilder,
                listDefinition: $spec,
                contextConfig: $config,
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