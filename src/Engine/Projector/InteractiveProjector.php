<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Projector;

use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\Context\Factory\AggregationContextFactory;
use HeimrichHannot\FlareBundle\Engine\Context\InteractiveContext;
use HeimrichHannot\FlareBundle\Engine\Context\PaginatedContextInterface;
use HeimrichHannot\FlareBundle\Engine\Loader\InteractiveEmptyLoader;
use HeimrichHannot\FlareBundle\Engine\Loader\InteractiveLoaderConfig;
use HeimrichHannot\FlareBundle\Engine\Loader\InteractiveLoaderInterface;
use HeimrichHannot\FlareBundle\Engine\View\AggregationView;
use HeimrichHannot\FlareBundle\Engine\View\InteractiveView;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Factory\FormHarnessFactory;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterData;
use HeimrichHannot\FlareBundle\Form\FormHarness;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\Paginator\Factory\PaginatorFactory;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use HeimrichHannot\FlareBundle\Reader\ReaderUrlGeneratorInterface;
use Symfony\Component\Form\FormInterface;

/**
 * @implements ProjectorInterface<InteractiveView>
 */
class InteractiveProjector extends AbstractProjector
{
    public function __construct(
        private readonly AggregationContextFactory $aggregationConfigFactory,
        private readonly FormHarnessFactory        $filterSetFactory,
        private readonly PaginatorFactory          $paginatorFactory,
    ) {}

    public function supports(ListSpec $list, ContextInterface $context): bool
    {
        return $context instanceof InteractiveContext;
    }

    public function project(ListSpec $list, ContextInterface $context): InteractiveView
    {
        \assert($context instanceof InteractiveContext, '$config must be an instance of InteractiveConfig');

        // collect filter values from form data
        $filterSet = $this->createFilterSet($list, $context);
        $form = $filterSet->getForm();
        $filterValues = $this->collectFilterData($list, $form);

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

        $readerUrlConfig = $context->createReaderUrlConfig();
        $readerUrlGenerator = $this->getReaderUrlGeneratorFactory()->create($readerUrlConfig);

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
        return $this->getLoaderFactory()->createInteractiveLoader($config);
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
    public function createForm(ListSpec $list, InteractiveContext $context): FormInterface
    {
        return $this->createFilterSet($list, $context)->getForm();
    }

    /**
     * Builds the list's filter set and hands the current request to its root form.
     *
     * @throws FlareException
     */
    protected function createFilterSet(ListSpec $list, InteractiveContext $context): FormHarness
    {
        $filterSet = $this->filterSetFactory->create($list, $context);

        $filterSet->getForm()->handleRequest($this->getCurrentRequest());

        return $filterSet;
    }

    /**
     * Collects each filter's form data, keyed by the filter's list-specification key.
     *
     * Filters that contribute nothing stay absent from the map, so a filter's programmatically
     * set data can take over downstream.
     *
     * @return array<string|int, FilterData>
     */
    protected function collectFilterData(ListSpec $list, FormInterface $form): array
    {
        $data = [];

        foreach ($list->filters as $key => $filter)
        {
            if (!$form->has($key)) {
                continue;
            }

            $child = $form->get($key);

            if ($child->getConfig()->getAttribute(FilterContext::ATTR_SINGLE_FIELD))
            {
                // Submitted value, or the field's configured default (e.g., a `preselect`) when
                // unsubmitted. Unsubmitted null defaults stay unset, so Filter::$data can take over.
                $value = $child->getData();

                if ($form->isSubmitted() || !\is_null($value)) {
                    $data[$key] = FilterData::single($value);
                }

                continue;
            }

            if ($form->isSubmitted())
            {
                $data[$key] = FilterData::of((array) $child->getData());
                continue;
            }

            // Unsubmitted forms never map the fields' default data (e.g., preselects) back onto
            // the compound filter child, so collect the defaults from the fields directly.
            // Filters without defaults stay unset here, so Filter::$data can take over.
            $values = \array_filter(
                \array_map(static fn (FormInterface $field): mixed => $field->getData(), $child->all()),
                static fn (mixed $value): bool => !\is_null($value),
            );

            if ($values) {
                $data[$key] = FilterData::of($values);
            }
        }

        return $data;
    }

    /**
     * @param array<string|int, FilterData> $filterValues
     *
     * @throws FlareException
     */
    protected function createAggregationView(
        ListSpec  $spec,
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
