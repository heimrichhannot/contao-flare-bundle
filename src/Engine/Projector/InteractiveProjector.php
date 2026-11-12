<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Projector;

use HeimrichHannot\FlareBundle\Engine\Intent\Factory\AggregationIntentFactory;
use HeimrichHannot\FlareBundle\Engine\Intent\Factory\ValidationIntentFactory;
use HeimrichHannot\FlareBundle\Engine\Intent\IntentInterface;
use HeimrichHannot\FlareBundle\Engine\Intent\InteractiveIntent;
use HeimrichHannot\FlareBundle\Engine\Intent\PaginatedIntentInterface;
use HeimrichHannot\FlareBundle\Engine\ListEntry\EntryCollector;
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
use HeimrichHannot\FlareBundle\List\Entry\EntryLookupInterface;
use HeimrichHannot\FlareBundle\List\FilterFailure;
use HeimrichHannot\FlareBundle\List\ListCompound;
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
        private readonly AggregationIntentFactory $aggregationIntentFactory,
        private readonly FormHarnessFactory       $formHarnessFactory,
        private readonly PaginatorFactory         $paginatorFactory,
        private readonly ValidationIntentFactory  $validationIntentFactory,
    ) {}

    public function supports(ListCompound $list, IntentInterface $intent): bool
    {
        return $intent instanceof InteractiveIntent;
    }

    /**
     * @throws FilterException In debug mode, if the list has failed.
     * @throws FlareException
     */
    public function project(ListCompound $list, IntentInterface $intent): InteractiveView
    {
        \assert($intent instanceof InteractiveIntent, '$intent must be an instance of InteractiveIntent');

        // the filter values decoded from the form: the submission, or the initial values when unsubmitted
        $harness = $this->createFormHarness($list, $intent);
        $form = $harness->getForm();
        $filterValues = $harness->decode();

        $entryCollectorFactory = $this->getEntryCollectorFactory();
        $readerUrlGenerator = $this->getReaderUrlGeneratorFactory()->create($intent->createReaderUrlConfig());

        // The links out of the list lead to the reader: lookups follow its rules, not the submitted form.
        $readerIntent = $this->validationIntentFactory->createFromIntent($intent);
        $lookup = $entryCollectorFactory->createLookup($list, $readerIntent);

        // The failures known without building conditions win over an invalid submission.
        $failures = \array_merge($list->failures, $harness->getFailures());

        if ($failures !== [])
        {
            $e = FilterFailure::toListException($failures, $list->source ?? 'list inlined', __METHOD__);

            return $this->createFailedView($e, $list, $intent, $form, $readerUrlGenerator, $lookup);
        }

        // An invalid submission shows no entries and is not counted.
        if ($form->isSubmitted() && !$form->isValid())
        {
            return $this->createView(
                loader: new InteractiveEmptyLoader(),
                form: $form,
                paginator: $this->paginatorFactory->createEmpty(pageParam: $this->getPageParam($form, $intent)),
                collector: $entryCollectorFactory->create($list, $intent, $readerUrlGenerator),
                lookup: $lookup,
                totalItems: 0,
            );
        }

        // pagination setup
        try
        {
            $totalItems = $this->createAggregationView($list, $intent, $filterValues)->getCount();
        }
        catch (FilterException $e)
        {
            return $this->createFailedView($e, $list, $intent, $form, $readerUrlGenerator, $lookup);
        }

        $paginator = $this->createPaginator($form, $intent, $totalItems);
        // override the intent to include the proper paginator config
        $intent = $intent->with(paginatorConfig: $paginator);

        $loader = $this->createLoader(new InteractiveLoaderConfig(
            list: $list,
            intent: $intent,
            filterValues: $filterValues,
        ));

        return $this->createView(
            loader: $loader,
            form: $form,
            paginator: $paginator,
            collector: $entryCollectorFactory->create($list, $intent, $readerUrlGenerator),
            lookup: $lookup,
            totalItems: $totalItems,
        );
    }

    /**
     * A list that cannot be created is not an empty list: the view is marked as failed and holds no
     * entries. The filter form still renders. In debug mode, the failure is rethrown instead.
     *
     * @throws FilterException In debug mode.
     */
    protected function createFailedView(
        FilterException             $e,
        ListCompound                $list,
        InteractiveIntent           $intent,
        FormInterface               $form,
        ReaderUrlGeneratorInterface $readerUrlGenerator,
        EntryLookupInterface        $lookup,
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
            paginator: $this->paginatorFactory->createEmpty(pageParam: $this->getPageParam($form, $intent)),
            collector: $this->getEntryCollectorFactory()->create($list, $intent, $readerUrlGenerator),
            lookup: $lookup,
            totalItems: 0,
            failed: true,
        );
    }

    protected function createLoader(InteractiveLoaderConfig $config): InteractiveLoaderInterface
    {
        return $this->getLoaderFactory()->createInteractiveLoader($config);
    }

    protected function createView(
        InteractiveLoaderInterface $loader,
        FormInterface              $form,
        Paginator                  $paginator,
        EntryCollector             $collector,
        EntryLookupInterface       $lookup,
        int                        $totalItems,
        bool                       $failed = false,
    ): InteractiveView {
        return new InteractiveView(
            loader: $loader,
            form: $form,
            paginator: $paginator,
            collector: $collector,
            lookup: $lookup,
            totalItems: $totalItems,
            failed: $failed,
        );
    }

    /**
     * @throws FlareException
     */
    public function createForm(ListCompound $list, InteractiveIntent $intent): FormInterface
    {
        return $this->createFormHarness($list, $intent)->getForm();
    }

    /**
     * Builds the list's form harness and hands the current request to its root form.
     *
     * @throws FlareException
     */
    protected function createFormHarness(ListCompound $list, InteractiveIntent $intent): FormHarness
    {
        $harness = $this->formHarnessFactory->create($list, $intent);

        $harness->getForm()->handleRequest($this->getCurrentRequest());

        return $harness;
    }

    /**
     * @param array<string, ?ValueInterface> $filterValues Filter values keyed by filter alias.
     *
     * @throws FlareException
     */
    protected function createAggregationView(
        ListCompound      $list,
        InteractiveIntent $interactiveIntent,
        array             $filterValues,
    ): AggregationView {
        $aggregationIntent = $this->aggregationIntentFactory->createFromIntent($interactiveIntent);

        $aggregationIntent = $aggregationIntent->withFilterValues($filterValues);

        $projector = $this->getProjectorFor($list, $aggregationIntent);
        $projection = $projector->project($list, $aggregationIntent);

        \assert($projection instanceof AggregationView, 'Expected AggregationView from projector.');

        return $projection;
    }

    public function createPaginator(FormInterface $form, PaginatedIntentInterface $intent, int $totalItems): Paginator
    {
        $pageParam = $this->getPageParam($form, $intent);

        if ($form->isSubmitted() && !$form->isValid()) {
            return $this->paginatorFactory->createEmpty(pageParam: $pageParam);
        }

        return $this->paginatorFactory->create(
            config: $intent->getPaginatorConfig()->with(totalItems: $totalItems),
            pageParam: $pageParam,
        );
    }

    protected function getPageParam(FormInterface $form, PaginatedIntentInterface $intent): string
    {
        $pageParam = $intent->getPaginatorQueryParameter() ?: $form->getName();
        $pageParam = $this->paginatorFactory->sanitizePageParam($pageParam);

        if ($pageParam === $form->getName()) {
            $pageParam .= '_page';
        }

        return $pageParam;
    }
}
