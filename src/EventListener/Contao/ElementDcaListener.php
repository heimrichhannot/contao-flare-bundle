<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\EventListener\Contao;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\Input;
use Contao\Message;
use Contao\StringUtil;
use HeimrichHannot\FlareBundle\Contract\DcaContract;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DataContainer\Inspection\FilterInspector;
use HeimrichHannot\FlareBundle\DataContainer\Inspection\FilterIssueKind;
use HeimrichHannot\FlareBundle\Engine\Context\ConfigurationContext;
use HeimrichHannot\FlareBundle\Event\FilterControlBuildDcaEvent;
use HeimrichHannot\FlareBundle\Event\FilterElementBuildDcaEvent;
use HeimrichHannot\FlareBundle\Event\ListDriverBuildDcaEvent;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterFactory;
use HeimrichHannot\FlareBundle\Filter\ElementScope;
use HeimrichHannot\FlareBundle\List\Factory\ListSpecBuilderFactory;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Query\Factory\ListExecutionContextFactory;
use HeimrichHannot\FlareBundle\Query\ListExecutionContext;
use HeimrichHannot\FlareBundle\Registry\FilterControlRegistry;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Registry\ListDriverRegistry;
use HeimrichHannot\FlareBundle\Registry\ValueProviderRegistry;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Applies each element's backend configuration ({@see DcaContract::buildDca()}) and the extension points
 * {@see ListDriverBuildDcaEvent}, {@see FilterElementBuildDcaEvent} and {@see FilterControlBuildDcaEvent}
 * to the live DCA of the edited record.
 *
 * The actual configuration runs as an appended `onload_callback` so that service callbacks
 * with positive priorities (e.g. prefix manipulation) execute first — matching the timing
 * of the previous palette assembly.
 */
#[AsHook('loadDataContainer', priority: -100)]
final readonly class ElementDcaListener
{
    public function __construct(
        private EventDispatcherInterface    $eventDispatcher,
        private FilterControlRegistry       $filterControlRegistry,
        private FilterElementRegistry       $filterElementRegistry,
        private FilterFactory               $filterFactory,
        private FilterInspector             $filterInspector,
        private ListExecutionContextFactory $listExecutionContextFactory,
        private ListSpecBuilderFactory      $listFactory,
        private ListDriverRegistry          $listDriverRegistry,
        private RequestStack                $requestStack,
        private TranslatorInterface         $translator,
        private ValueProviderRegistry       $valueProviderRegistry,
    ) {}

    public function __invoke(string $table): void
    {
        if ($table !== FilterModel::getTable() && $table !== ListModel::getTable()) {
            return;
        }

        if (!isset($GLOBALS['TL_DCA'][$table]['fields'])) {
            return;
        }

        if (!$rawId = Input::get('id')) {
            return;
        }

        $filterOptions = [
            'options' => ['min_range' => 1],
            'flags' => \FILTER_NULL_ON_FAILURE,
        ];

        if (!$id = \filter_var($rawId, \FILTER_VALIDATE_INT, $filterOptions)) {
            return;
        }

        $this->configure($table, (int) $id);
    }

    private function configure(string $table, int $id): void
    {
        $type = '';
        $service = null;

        if ($table === FilterModel::getTable())
        {
            $filterModel = FilterModel::findByPk($id);
            $listModel = $filterModel?->getRelated('pid');
            $type = (string) ($filterModel->type ?? '');
            $service = $this->filterElementRegistry->getService($type);
        }
        /** @mago-expect lint:no-else-clause This else clause is fine. */
        else
        {
            $filterModel = null;

            if ($listModel = ListModel::findByPk($id))
            {
                $type = (string) ($listModel->type ?? '');
                $service = $this->listDriverRegistry->getService($type);

                $this->reportFilterStates($listModel);
            }
        }

        if (!$listModel instanceof ListModel || !$type || $type === 'default' || \str_starts_with($type, '__')) {
            return;
        }

        // The DCA is loaded on every backend request to the table, list views included, but only option
        // callbacks need the list spec. Building it runs the whole list build (all filters, schema
        // resolution) and fails on broken configurations, so it is built on first use and at most once.
        $spec = null;
        $getSpec = function () use (&$spec, $listModel): ListSpec {
            return $spec ??= $this->listFactory->createFromListModel($listModel)->build();
        };

        $context = new DcaContext(
            table: $table,
            type: $type,
            listModel: $listModel,
            filterModel: $filterModel,
            executionContextFactory: fn (): ?ListExecutionContext => $this->createExecutionContext($getSpec),
            element: $service instanceof FilterElementInterface ? $service : null,
            elementScopeFactory: fn (): ?ElementScope => $this->createElementScope($getSpec, $filterModel),
        );

        $dcaBuilder = new DcaBuilder();

        if ($service instanceof DcaContract) {
            $service->buildDca($dcaBuilder, $context);
        }

        if ($filterModel !== null) {
            $this->buildFrameworkDca($dcaBuilder, $context, $filterModel);
        }

        $event = match ($table) {
            FilterModel::getTable() => new FilterElementBuildDcaEvent($dcaBuilder, $context),
            ListModel::getTable() => new ListDriverBuildDcaEvent($dcaBuilder, $context),
            default => throw new \LogicException("Unexpected table {$table}"),
        };

        $this->eventDispatcher->dispatch($event);

        $isEditAction = $this->requestStack->getCurrentRequest()?->query->get('act') === 'edit';

        $dcaBuilder->apply($table, $type, applyPalette: $isEditAction);

        if ($isEditAction && $filterModel !== null && $this->reportsFilterStates()) {
            $this->reportFilterState($listModel, $filterModel);
        }
    }

    /**
     * Whether to report filter states: on GET requests only. The DCA is loaded before Contao saves a submitted form,
     * and a message added then would be flashed across the redirect, reporting the state from before the save.
     */
    private function reportsFilterStates(): bool
    {
        return $this->requestStack->getCurrentRequest()?->isMethod('GET') ?? false;
    }

    /**
     * On the filter's edit form: each of its issues ({@see FilterInspector}), in its kind's severity.
     */
    private function reportFilterState(ListModel $listModel, FilterModel $filterModel): void
    {
        foreach ($this->filterInspector->inspect($listModel)->forRow((int) $filterModel->id) as $issue) {
            self::addMessage($issue->kind, StringUtil::specialchars($issue->describe($this->translator)));
        }
    }

    /**
     * Contao renders messages raw; the caller escapes what it passes.
     */
    private static function addMessage(FilterIssueKind $kind, string $message): void
    {
        if ($kind === FilterIssueKind::Ineffective)
        {
            Message::addNew($message);

            return;
        }

        Message::addError($message);
    }

    /**
     * The framework's part of the palette (§5.2, §6.1): the selected control fills the control segment,
     * and an element with a value class shows the `value` field after the framework prefix.
     */
    private function buildFrameworkDca(DcaBuilder $builder, DcaContext $context, FilterModel $filterModel): void
    {
        $controlType = (string) $filterModel->control;

        if ($control = $this->filterControlRegistry->get($controlType))
        {
            $controlBuilder = $builder->scope('control');

            if ($control instanceof DcaContract) {
                $control->buildDca($controlBuilder, $context);
            }

            $this->eventDispatcher->dispatch(new FilterControlBuildDcaEvent($controlBuilder, $context, $controlType));
        }

        $valueClass = $this->filterElementRegistry->getAttribute($context->type)?->value;

        if ($valueClass === null) {
            return;
        }

        $this->valueProviderRegistry->get($valueClass)?->buildDcaField($builder->field('value'), $context);

        $builder->composePrefix(static fn (string $prefix): string => $prefix . ',value');
    }

    /**
     * @mago-expect lint:no-empty-catch-clause Backend configuration must not fail on broken list configs.
     */
    private function createExecutionContext(\Closure $getSpec): ?ListExecutionContext
    {
        try
        {
            return $this->listExecutionContextFactory->create($getSpec());
        }
        catch (\Exception) {}

        return null;
    }

    /**
     * The backend scope (§6.4): it knows the control, which decides the element's options, but resolves no value, so
     * an unreadable value cannot block the form that repairs it.
     *
     * @mago-expect lint:no-empty-catch-clause Backend configuration must not fail on broken list configs.
     */
    private function createElementScope(\Closure $getSpec, ?FilterModel $filterModel): ?ElementScope
    {
        if ($filterModel === null) {
            return null;
        }

        try
        {
            return new ElementScope(
                list: $getSpec(),
                filter: $this->filterFactory->createFromFilterModel($filterModel, withValue: false),
                engineContext: new ConfigurationContext(),
            );
        }
        catch (\Exception) {}

        return null;
    }

    /**
     * On the list's filter overview (its child view, which loads this DCA as the parent table): how many published
     * filters have an issue of each kind ({@see FilterInspector}), in the kinds' order; each filter row is marked in
     * the overview as well.
     */
    private function reportFilterStates(ListModel $listModel): void
    {
        $query = $this->requestStack->getCurrentRequest()?->query;

        if ($query?->get('table') !== FilterModel::getTable() || $query->get('act') || !$this->reportsFilterStates()) {
            return;
        }

        $inspection = $this->filterInspector->inspect($listModel);

        foreach (FilterIssueKind::cases() as $kind)
        {
            if (!$count = $inspection->count($kind)) {
                continue;
            }

            $parameters = match ($kind) {
                FilterIssueKind::DuplicateAlias => ['%alias%' => \implode(', ', \array_map(
                    static fn (string $alias): string => '"' . StringUtil::specialchars($alias) . '"',
                    $inspection->blockingAliases,
                ))],
                default => ['%count%' => $count],
            };

            $id = match ($kind) {
                FilterIssueKind::ProgrammingError => 'list.info.programming_errors',
                FilterIssueKind::DuplicateAlias => 'list.info.duplicate_filter_alias',
                FilterIssueKind::Broken => 'list.info.broken_filters',
                FilterIssueKind::Ineffective => 'list.info.ineffective_filters',
            };

            self::addMessage($kind, $this->translator->trans($id, $parameters, 'flare'));
        }
    }
}
