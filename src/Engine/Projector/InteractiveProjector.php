<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Projector;

use HeimrichHannot\FlareBundle\Contract\FilterElement\HydrateFormContract;
use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\Context\Factory\AggregationContextFactory;
use HeimrichHannot\FlareBundle\Engine\Context\InteractiveContext;
use HeimrichHannot\FlareBundle\Engine\Context\Interface\PaginatedContextInterface;
use HeimrichHannot\FlareBundle\Engine\View\AggregationView;
use HeimrichHannot\FlareBundle\Engine\View\InteractiveView;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Form\Factory\FilterFormFactory;
use HeimrichHannot\FlareBundle\Generator\ReaderPageUrlGenerator;
use HeimrichHannot\FlareBundle\Paginator\Factory\PaginatorFactory;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use HeimrichHannot\FlareBundle\Query\ListQueryConfig;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use Symfony\Component\Form\Exception\OutOfBoundsException;
use Symfony\Component\Form\FormInterface;

/**
 * @implements ProjectorInterface<InteractiveView>
 */
class InteractiveProjector extends AbstractProjector
{
    public function __construct(
        private readonly AggregationContextFactory $aggregationConfigFactory,
        private readonly FilterFormFactory         $filterFormFactory,
        private readonly PaginatorFactory          $paginatorFactory,
        private readonly ReaderPageUrlGenerator    $readerPageUrlGenerator,
    ) {}

    public function supports(ListSpecification $list, ContextInterface $context): bool
    {
        return $context instanceof InteractiveContext;
    }

    public function project(ListSpecification $list, ContextInterface $context): InteractiveView
    {
        \assert($context instanceof InteractiveContext, '$config must be an instance of InteractiveConfig');

        $form = $this->getForm($list, $context);
        $filterValues = $this->gatherFilterValues($list, $this->mapFormDataToFilterKeys($list, $form));
        $totalItems = $this->getAggregationProjection($list, $context, $filterValues)->getCount();
        $paginator = $this->getPaginator($form, $context, $totalItems);

        // Override list context to include the proper paginator config
        $context = $context->with(paginatorConfig: $paginator);

        $queryConfig = new ListQueryConfig(
            list: $list,
            context: $context,
            filterValues: $filterValues,
        );

        if ($form->isSubmitted() && !$form->isValid()) {
            $fetchEntries = static fn (): array => [];
        }

        $fetchEntries ??= fn (): array => $this->fetchEntries($queryConfig);

        $readerUrlGenerator = $this->readerPageUrlGenerator->createCallable($context);

        return $this->createView(
            fetchEntries: $fetchEntries,
            form: $form,
            paginator: $paginator,
            readerUrlGenerator: $readerUrlGenerator,
            table: $list->dc,
            totalItems: $totalItems,
        );
    }

    protected function createView(
        \Closure $fetchEntries,
        FormInterface $form,
        Paginator $paginator,
        \Closure $readerUrlGenerator,
        string $table,
        int $totalItems,
    ): InteractiveView {
        return new InteractiveView(
            fetchEntries: $fetchEntries,
            form: $form,
            paginator: $paginator,
            readerUrlGenerator: $readerUrlGenerator,
            table: $table,
            totalItems: $totalItems,
        );
    }

    /**
     * @throws FlareException
     */
    public function getForm(ListSpecification $list, InteractiveContext $context): FormInterface
    {
        $form = $this->filterFormFactory->create($list, $context);
        $form->handleRequest($this->getCurrentRequest());

        $this->hydrateForm($form, $list);

        return $form;
    }

    /**
     * @throws FlareException If the form does not contain the filter field.
     */
    private function hydrateForm(FormInterface $form, ListSpecification $list): void
    {
        if ($form->isSubmitted()) {
            return;
        }

        $filterElementRegistry = $this->getFilterElementRegistry();

        foreach ($list->getFilters()->getIterator() as $filterDefinition)
        {
            if (!$filterElement = $filterElementRegistry->get($filterDefinition->getType())?->getService()) {
                continue;
            }

            if (!$filterElement instanceof HydrateFormContract) {
                continue;
            }

            if ($filterDefinition->isIntrinsic()) {
                continue;
            }

            if (!$filterName = $filterDefinition->getAlias()) {
                throw new FlareException(message: 'Non-intrinsic filter must provide a form field name.');
            }

            if (!$form->has($filterName)) {
                continue;
            }

            try
            {
                $field = $form->get($filterName);
            }
            catch (OutOfBoundsException $exception)
            {
                $filerModel = $filterDefinition->getDataSource();

                throw new FlareException(
                    message: 'Filter form does not contain field: ' . $filterName,
                    previous: $exception,
                    method: __METHOD__,
                    source: $filerModel ? \sprintf('tl_flare_filter.id=%s', $filerModel->id) : 'filter inlined'
                );
            }

            $filterElement->hydrateForm($field, $list, $filterDefinition);
        }
    }

    protected function mapFormDataToFilterKeys(ListSpecification $list, FormInterface $form): array
    {
        if (!$formData = $form->getData()) {
            return [];
        }

        $values = [];

        foreach ($list->getFilters()->all() as $key => $definition)
        {
            $formName = $definition->getAlias();
            if ($formName && \array_key_exists($formName, $formData)) {
                $values[$key] = $form->get($formName)->getViewData();
            }
        }

        return $values;
    }

    /**
     * @throws FlareException
     */
    protected function getAggregationProjection(
        ListSpecification  $spec,
        InteractiveContext $interactiveConfig,
        array              $filterValues,
    ): AggregationView {
        $aggregationConfig = $this->aggregationConfigFactory->createFromConfig($interactiveConfig);

        $aggregationConfig = $aggregationConfig->withFilterValues($filterValues);

        $projector = $this->getProjectorFor($spec, $aggregationConfig);
        $projection = $projector->project($spec, $aggregationConfig);

        \assert($projection instanceof AggregationView, 'Expected AggregationView from projector.');

        return $projection;
    }

    public function getPaginator(FormInterface $form, PaginatedContextInterface $context, int $totalItems): Paginator
    {
        $pageParam = $context->getPaginatorQueryParameter() ?: $form->getName();
        $pageParam = $this->paginatorFactory->sanitizePageParam($pageParam);
        if ($pageParam === $form->getName()) {
            $pageParam .= '_page';
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            return $this->paginatorFactory->createEmpty(pageParam: $pageParam);
        }

        return $this->paginatorFactory->create(
            config: $context->getPaginatorConfig()->with(totalItems: $totalItems),
            pageParam: $pageParam,
        );
    }

    /**
     * @throws FlareException|FilterException
     */
    public function fetchEntries(ListQueryConfig $queryConfig): array
    {
        try
        {
            $qb = $this->createQueryBuilder($queryConfig);

            if (!$qb) {
                return [];
            }

            $result = $qb->executeQuery();

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
            throw new FlareException($e->getMessage(), $e->getCode(), $e, method: __METHOD__);
        }
    }
}