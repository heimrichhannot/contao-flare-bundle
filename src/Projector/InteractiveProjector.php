<?php

namespace HeimrichHannot\FlareBundle\Projector;

use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Context\ContextConfigInterface;
use HeimrichHannot\FlareBundle\Context\Factory\AggregationConfigFactory;
use HeimrichHannot\FlareBundle\Context\InteractiveConfig;
use HeimrichHannot\FlareBundle\Context\Interface\PaginatedContextInterface;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Factory\PaginatorBuilderFactory;
use HeimrichHannot\FlareBundle\Generator\ReaderPageUrlGenerator;
use HeimrichHannot\FlareBundle\Manager\FilterFormManager;
use HeimrichHannot\FlareBundle\Manager\ListQueryManager;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use HeimrichHannot\FlareBundle\Projector\Registry\ProjectorRegistry;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use HeimrichHannot\FlareBundle\View\AggregationView;
use HeimrichHannot\FlareBundle\View\InteractiveView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProjectorInterface<InteractiveView>
 */
class InteractiveProjector extends AbstractProjector
{
    public function __construct(
        private readonly AggregationConfigFactory $aggregationConfigFactory,
        private readonly Connection               $connection,
        private readonly FilterFormManager        $filterFormManager,
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
        \assert($config instanceof InteractiveConfig, '$config must be an instance of InteractiveConfig');

        $form = $this->getForm($spec, $config);
        $filterValues = $this->mapFormDataToFilterKeys($spec, $form);
        $totalItems = $this->getAggregationProjection($spec, $config, $filterValues)->getCount();
        $paginator = $this->getPaginator($form, $config, $totalItems);

        // Override list context to include the proper paginator config
        $config = $config->with(paginatorConfig: $paginator);

        if ($form->isSubmitted() && !$form->isValid()) {
            $fetchEntries = static fn (): array => [];
        }

        $fetchEntries ??= function () use ($spec, $config, $filterValues): array {
            return $this->fetchEntries($spec, $config, $filterValues);
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

    private function mapFormDataToFilterKeys(ListSpecification $spec, FormInterface $form): array
    {
        if (!$formData = $form->getData()) {
            return [];
        }

        $values = [];

        foreach ($spec->getFilters()->all() as $key => $definition)
        {
            $formName = $definition->getFilterFormFieldName();
            if ($formName && \array_key_exists($formName, $formData)) {
                $values[$key] = $formData[$formName];
            }
        }

        return $values;
    }

    private function getAggregationProjection(
        ListSpecification $spec,
        InteractiveConfig $interactiveConfig,
        array             $filterValues,
    ): AggregationView {
        $aggregationConfig = $this->aggregationConfigFactory->createFromConfig($interactiveConfig);

        $aggregationConfig = $aggregationConfig->withFilterValues($filterValues);

        $projector = $this->projectorRegistry->getProjectorFor($spec, $aggregationConfig);
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

    public function fetchEntries(
        ListSpecification $spec,
        InteractiveConfig $config,
        array             $filterValues
    ): array {
        try
        {
            $listQueryBuilder = $this->listQueryManager->prepare($spec);

            $this->listQueryManager->populate($listQueryBuilder, $spec, $config, $filterValues);

            $query = $this->listQueryManager->populate(
                listQueryBuilder: $listQueryBuilder,
                listSpecification: $spec,
                contextConfig: $config,
                filterValues: $filterValues,
            );

            if (!$query->isAllowed())
            {
                return [];
            }

            $result = $query->execute($this->connection);

            $entries = $result->fetchAllAssociative();

            $result->free();

            return $entries;
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