<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\EventListener\Contao;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\Input;
use Contao\Message;
use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Contract\DcaContract;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DataContainer\IneffectiveFilterInspector;
use HeimrichHannot\FlareBundle\Engine\Context\ConfigurationContext;
use HeimrichHannot\FlareBundle\Event\FilterControlBuildDcaEvent;
use HeimrichHannot\FlareBundle\Event\FilterElementBuildDcaEvent;
use HeimrichHannot\FlareBundle\Event\ListDriverBuildDcaEvent;
use HeimrichHannot\FlareBundle\EventListener\DataContainer\FlareFilter\ListCallbacks;
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
        private Connection                  $connection,
        private EventDispatcherInterface    $eventDispatcher,
        private FilterControlRegistry       $filterControlRegistry,
        private FilterElementRegistry       $filterElementRegistry,
        private FilterFactory               $filterFactory,
        private IneffectiveFilterInspector  $ineffectiveFilterInspector,
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

                $this->checkDuplicateFilterAliases($listModel);
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

        if ($isEditAction && $filterModel !== null
            && $reason = $this->ineffectiveFilterInspector->describe($filterModel))
        {
            Message::addInfo($reason);
        }
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
     * The backend scope (§6.4): options need neither a control nor a value, so none is resolved.
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
                filter: $this->filterFactory->createFromFilterModel($filterModel, withControlAndValue: false),
                engineContext: new ConfigurationContext(),
            );
        }
        catch (\Exception) {}

        return null;
    }

    private function checkDuplicateFilterAliases(ListModel $listModel): void
    {
        $qTable = $this->connection->quoteIdentifier(FilterModel::getTable());

        // The same filters the list collects; compared as their runtime aliases, which are normalized form names.
        $sql = <<<SQL
            SELECT `id`, `alias`
            FROM {$qTable}
            WHERE `pid` = :pid
              AND `published` = 1
              AND `tstamp` > 0
        SQL;

        $duplicateAliases = FilterModel::findDuplicateFormNames($this->connection->fetchAllAssociative($sql, [
            'pid' => (int) $listModel->id,
        ]));

        if (!$duplicateAliases) {
            return;
        }

        /** Used in {@see ListCallbacks} to notify user of duplicate filter aliases in the Contao backend. */
        $GLOBALS['FLARE']['duplicate_filter_aliases'] = $duplicateAliases;

        Message::addError($this->translator->trans('list.info.duplicate_filter_alias', [
            '%alias%' => implode(', ', \array_map(
                static fn (string $alias): string => "\"{$alias}\"",
                $duplicateAliases
            )),
        ], 'flare'));
    }
}
