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
use HeimrichHannot\FlareBundle\Engine\Context\ConfigurationContext;
use HeimrichHannot\FlareBundle\Event\ElementDcaEvent;
use HeimrichHannot\FlareBundle\EventListener\DataContainer\FlareFilter\ListCallbacks;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterFactory;
use HeimrichHannot\FlareBundle\Filter\FilterScope;
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
 * Applies each element's backend configuration ({@see DcaContract::configureDca()}) and
 * the {@see ElementDcaEvent} extension point to the live DCA of the edited record.
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

        if (!Input::get('id')) {
            return;
        }

        $this->configure($table);
    }

    private function configure(string $table): void
    {
        if (!$id = Input::get('id')) {
            return;
        }

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
            filterScopeFactory: fn (): ?FilterScope => $this->createFilterScope($getSpec, $filterModel),
        );

        $dca = new DcaBuilder();

        if ($service instanceof DcaContract) {
            $service->buildDca($dca, $context);
        }

        if ($filterModel !== null) {
            $this->buildFrameworkDca($dca, $context, $filterModel);
        }

        $this->eventDispatcher->dispatch(new ElementDcaEvent($dca, $context));

        $isEditAction = $this->requestStack->getCurrentRequest()?->query->get('act') === 'edit';

        $dca->apply($table, $type, applyPalette: $isEditAction);
    }

    /**
     * The framework's part of the palette (§5.2, §6.1): the selected control fills the control segment,
     * and an element with a value class shows the `value` field after the framework prefix.
     */
    private function buildFrameworkDca(DcaBuilder $dca, DcaContext $context, FilterModel $filterModel): void
    {
        $control = $this->filterControlRegistry->get((string) $filterModel->control);

        if ($control instanceof DcaContract) {
            $control->buildDca($dca->scope('control'), $context);
        }

        $valueClass = $this->filterElementRegistry->getAttribute($context->type)?->value;

        if ($valueClass === null) {
            return;
        }

        $this->valueProviderRegistry->get($valueClass)?->buildDcaField($dca->field('value'), $context);

        $dca->composePrefix(static fn (string $prefix): string => $prefix . ',value');
    }

    /**
     * @mago-expect lint:no-empty-catch-clause Backend configuration must not fail on broken list configs.
     */
    private function createExecutionContext(\Closure $getSpec): ?ListExecutionContext
    {
        try {
            return $this->listExecutionContextFactory->create($getSpec());
        }
        catch (\Throwable) {}

        return null;
    }

    /**
     * The backend scope (§6.4): options need neither a control nor a value, so none is resolved.
     *
     * @mago-expect lint:no-empty-catch-clause Backend configuration must not fail on broken list configs.
     */
    private function createFilterScope(\Closure $getSpec, ?FilterModel $filterModel): ?FilterScope
    {
        if ($filterModel === null) {
            return null;
        }

        try
        {
            return new FilterScope(
                list: $getSpec(),
                filter: $this->filterFactory->createFromFilterModel($filterModel, withControlAndValue: false),
                engineContext: new ConfigurationContext(),
            );
        }
        catch (\Throwable) {}

        return null;
    }

    private function checkDuplicateFilterAliases(ListModel $listModel): void
    {
        $qTable = $this->connection->quoteIdentifier(FilterModel::getTable());

        $sql = <<<SQL
            SELECT `alias`
            FROM {$qTable}
            WHERE `pid` = :pid
              AND `published` = 1
              AND `tstamp` > 0
              AND `alias` IS NOT NULL
              AND `alias` <> ''
            GROUP BY `alias`
            HAVING COUNT(*) > 1
        SQL;

        $duplicateAliases = $this->connection->fetchFirstColumn($sql, [
            'pid' => $listModel->id,
        ]);

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
