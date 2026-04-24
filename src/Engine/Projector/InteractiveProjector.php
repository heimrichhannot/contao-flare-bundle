<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Projector;

use HeimrichHannot\FlareBundle\Contract\FilterElement\HydrateFormContract;
use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\Context\Factory\AggregationContextFactory;
use HeimrichHannot\FlareBundle\Engine\Context\InteractiveContext;
use HeimrichHannot\FlareBundle\Engine\Context\Interface\PaginatedContextInterface;
use HeimrichHannot\FlareBundle\Engine\Factory\LoaderFactory;
use HeimrichHannot\FlareBundle\Engine\Loader\InteractiveEmptyLoader;
use HeimrichHannot\FlareBundle\Engine\Loader\InteractiveLoaderConfig;
use HeimrichHannot\FlareBundle\Engine\Loader\InteractiveLoaderInterface;
use HeimrichHannot\FlareBundle\Engine\View\AggregationView;
use HeimrichHannot\FlareBundle\Engine\View\InteractiveView;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Form\Factory\FilterFormFactory;
use HeimrichHannot\FlareBundle\Paginator\Factory\PaginatorFactory;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use HeimrichHannot\FlareBundle\Reader\Factory\ReaderUrlGeneratorFactory;
use HeimrichHannot\FlareBundle\Reader\ReaderUrlGeneratorInterface;
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
        private readonly LoaderFactory             $loaderFactory,
        private readonly PaginatorFactory          $paginatorFactory,
        private readonly ReaderUrlGeneratorFactory $readerUrlGeneratorFactory,
    ) {}

    public function supports(ListSpecification $list, ContextInterface $context): bool
    {
        return $context instanceof InteractiveContext;
    }

    public function project(ListSpecification $list, ContextInterface $context): InteractiveView
    {
        \assert($context instanceof InteractiveContext, '$config must be an instance of InteractiveConfig');

        // collect filter values from form data
        $form = $this->createForm($list, $context);
        $runtimeValues = $this->mapFormDataToFilterKeys($list, $form);
        $filterValues = $this->resolveFilterValues($list, $runtimeValues);

        // pagination setup
        $totalItems = $this->createAggregationView($list, $context, $filterValues)->getCount();
        $paginator = $this->createPaginator($form, $context, $totalItems);
        // override list context to include the proper paginator config
        $context = $context->with(paginatorConfig: $paginator);

        // if form is submitted but invalid, create empty loader
        if ($form->isSubmitted() && !$form->isValid()) {
            $loader = new InteractiveEmptyLoader();
        }

        // if form is valid or not submitted, create proper loader
        if (!isset($loader)) {
            $config = new InteractiveLoaderConfig(
                list: $list,
                context: $context,
                filterValues: $filterValues,
            );

            $loader = $this->createLoader($config);
        }

        $readerUrlGenerator = $this->readerUrlGeneratorFactory->create($context->createReaderUrlConfig());

        return $this->createView(
            loader: $loader,
            form: $form,
            paginator: $paginator,
            readerUrlGenerator: $readerUrlGenerator,
            table: $list->dc,
            totalItems: $totalItems,
        );
    }

    protected function createLoader(InteractiveLoaderConfig $config): InteractiveLoaderInterface
    {
        return $this->loaderFactory->createInteractiveLoader($config);
    }

    protected function createView(
        InteractiveLoaderInterface  $loader,
        FormInterface               $form,
        Paginator                   $paginator,
        ReaderUrlGeneratorInterface $readerUrlGenerator,
        string                      $table,
        int                         $totalItems,
    ): InteractiveView {
        return new InteractiveView(
            loader: $loader,
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
    public function createForm(ListSpecification $list, InteractiveContext $context): FormInterface
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
                $filterSourceId = $filterDefinition->getDataSource()->getFilterIdentifier();

                throw new FlareException(
                    message: 'Filter form does not contain field: ' . $filterName,
                    previous: $exception,
                    method: __METHOD__,
                    source: $filterSourceId ? \sprintf('tl_flare_filter.id=%s', $filterSourceId) : 'filter inlined'
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
                $values[$key] = $form->get($formName)->getData();
            }
        }

        return $values;
    }

    /**
     * @throws FlareException
     */
    protected function createAggregationView(
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

    public function createPaginator(FormInterface $form, PaginatedContextInterface $context, int $totalItems): Paginator
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
}