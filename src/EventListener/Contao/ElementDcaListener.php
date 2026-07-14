<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\EventListener\Contao;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\Input;
use HeimrichHannot\FlareBundle\Contract\DcaContract;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\Event\ElementDcaEvent;
use HeimrichHannot\FlareBundle\List\Factory\ListBuilderFactory;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Query\Factory\ListExecutionContextFactory;
use HeimrichHannot\FlareBundle\Query\ListExecutionContext;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Registry\ListTypeRegistry;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Applies each element's backend configuration ({@see DcaContract::configureDca()}) and
 * the {@see ElementDcaEvent} extension point to the live DCA of the edited record.
 *
 * The actual configuration runs as an appended `onload_callback` so that service callbacks
 * with positive priorities (e.g. prefix manipulation) execute first — matching the timing
 * of the previous palette assembly.
 */
#[AsHook('loadDataContainer', priority: -100)]
readonly class ElementDcaListener
{
    public function __construct(
        private EventDispatcherInterface    $eventDispatcher,
        private FilterElementRegistry       $filterElementRegistry,
        private ListExecutionContextFactory $listExecutionContextFactory,
        private ListBuilderFactory          $listFactory,
        private ListTypeRegistry            $listTypeRegistry,
        private RequestStack                $requestStack,
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

        $GLOBALS['TL_DCA'][$table]['config']['onload_callback'][] = function () use ($table): void {
            $this->configure($table);
        };
    }

    private function configure(string $table): void
    {
        if (!$id = Input::get('id')) {
            return;
        }

        if ($table === FilterModel::getTable())
        {
            $filterModel = FilterModel::findByPk($id);
            $listModel = $filterModel?->getRelated('pid');
            $type = (string) ($filterModel->type ?? '');
            $service = $this->filterElementRegistry->get($type)?->getService();
        }
        /** @mago-expect lint:no-else-clause This else clause is fine. */
        else
        {
            $filterModel = null;
            $listModel = ListModel::findByPk($id);
            $type = (string) ($listModel->type ?? '');
            $service = $this->listTypeRegistry->get($type)?->getService();
        }

        if (!$listModel instanceof ListModel || !$type || $type === 'default' || \str_starts_with($type, '__')) {
            return;
        }

        $context = new DcaContext(
            table: $table,
            type: $type,
            listModel: $listModel,
            filterModel: $filterModel,
            executionContextFactory: fn (): ?ListExecutionContext => $this->createExecutionContext($listModel),
        );

        $dca = new DcaBuilder();

        if ($service instanceof DcaContract) {
            $service->buildDca($dca, $context);
        }

        $this->eventDispatcher->dispatch(new ElementDcaEvent($dca, $context));

        $isEditAction = $this->requestStack->getCurrentRequest()?->query->get('act') === 'edit';

        $dca->apply($table, $type, applyPalette: $isEditAction);
    }

    /**
     * @mago-expect lint:no-empty-catch-clause Backend configuration must not fail on broken list configs.
     */
    private function createExecutionContext(ListModel $listModel): ?ListExecutionContext
    {
        try
        {
            $specification = $this->listFactory->createFromListModel($listModel)->build();

            return $this->listExecutionContextFactory->create($specification);
        }
        catch (\Throwable) {}

        return null;
    }
}
