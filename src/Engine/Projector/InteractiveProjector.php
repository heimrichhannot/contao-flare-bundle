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
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Form\Factory\FormHarnessFactory;
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
        private readonly FormHarnessFactory        $formHarnessFactory,
        private readonly PaginatorFactory          $paginatorFactory,
    ) {}

    public function supports(ListSpec $list, ContextInterface $context): bool
    {
        return $context instanceof InteractiveContext;
    }

    /**
     * @throws FilterException In debug mode, if the list has failed.
     * @throws FlareException
     */
    public function project(ListSpec $list, ContextInterface $context): InteractiveView
    {
        \assert($context instanceof InteractiveContext, '$config must be an instance of InteractiveConfig');

        // the filter values decoded from the form: the submission, or the initial values when unsubmitted
        $harness = $this->createFormHarness($list, $context);
        $form = $harness->getForm();
        $filterValues = $harness->decode();

        $readerUrlConfig = $context->createReaderUrlConfig();
        $readerUrlGenerator = $this->getReaderUrlGeneratorFactory()->create($readerUrlConfig);

        // pagination setup
        try
        {
            $totalItems = $this->createAggregationView($list, $context, $filterValues)->getCount();
        }
        catch (FilterException $e)
        {
            return $this->createFailedView($e, $list, $context, $form, $readerUrlGenerator);
        }

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

        return $this->createView(
            loader: $loader,
            form: $form,
            paginator: $paginator,
            readerUrlGenerator: $readerUrlGenerator,
            table: $list->dc,
            totalItems: $totalItems,
        );
    }

    /**
     * A list that cannot be created is not an empty list (SPEC_FILTER_FORMS.md §8.1): the view is
     * marked as failed and holds no entries. The filter form still renders. In debug mode, the
     * failure is rethrown instead.
     *
     * @throws FilterException In debug mode.
     */
    protected function createFailedView(
        FilterException             $e,
        ListSpec                    $list,
        InteractiveContext          $context,
        FormInterface               $form,
        ReaderUrlGeneratorInterface $readerUrlGenerator,
    ): InteractiveView {
        $this->getLogger()->error(\sprintf(
            '[FLARE] The list %s could not be created: %s',
            $list->source ?? 'inlined',
            $e->getMessage(),
        ), ['method' => __METHOD__, 'exception' => $e]);

        if ($this->isDebug()) {
            throw $e;
        }

        return $this->createView(
            loader: new InteractiveEmptyLoader(),
            form: $form,
            paginator: $this->paginatorFactory->createEmpty(pageParam: $this->getPageParam($form, $context)),
            readerUrlGenerator: $readerUrlGenerator,
            table: $list->dc,
            totalItems: 0,
            failed: true,
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
        bool                        $failed = false,
    ): InteractiveView {
        return new InteractiveView(
            loader: $loader,
            form: $form,
            paginator: $paginator,
            readerUrlGenerator: $readerUrlGenerator,
            table: $table,
            totalItems: $totalItems,
            failed: $failed,
        );
    }

    /**
     * @throws FlareException
     */
    public function createForm(ListSpec $list, InteractiveContext $context): FormInterface
    {
        return $this->createFormHarness($list, $context)->getForm();
    }

    /**
     * Builds the list's form harness and hands the current request to its root form.
     *
     * @throws FlareException
     */
    protected function createFormHarness(ListSpec $list, InteractiveContext $context): FormHarness
    {
        $harness = $this->formHarnessFactory->create($list, $context);

        $harness->getForm()->handleRequest($this->getCurrentRequest());

        return $harness;
    }

    /**
     * @param array<string, ?ValueInterface> $filterValues Filter values keyed by filter alias.
     *
     * @throws FlareException
     */
    protected function createAggregationView(
        ListSpec           $spec,
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
        $pageParam = $this->getPageParam($form, $context);

        if ($form->isSubmitted() && !$form->isValid()) {
            return $this->paginatorFactory->createEmpty(pageParam: $pageParam);
        }

        return $this->paginatorFactory->create(
            config: $context->getPaginatorConfig()->with(totalItems: $totalItems),
            pageParam: $pageParam,
        );
    }

    protected function getPageParam(FormInterface $form, PaginatedContextInterface $context): string
    {
        $pageParam = $context->getPaginatorQueryParameter() ?: $form->getName();
        $pageParam = $this->paginatorFactory->sanitizePageParam($pageParam);

        if ($pageParam === $form->getName()) {
            $pageParam .= '_page';
        }

        return $pageParam;
    }
}
