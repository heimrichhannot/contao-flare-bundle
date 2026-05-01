<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\DataContainer;

use Contao\DataContainer;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Manager\FlareCallbackManager;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Query\Factory\ListExecutionContextFactory;
use HeimrichHannot\FlareBundle\Query\ListExecutionContext;
use HeimrichHannot\FlareBundle\Specification\Factory\ConfiguredFilterFactory;
use HeimrichHannot\FlareBundle\Specification\Factory\ListSpecificationFactory;
use HeimrichHannot\FlareBundle\Specification\ConfiguredFilter;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use HeimrichHannot\FlareBundle\Util\CallbackHelper;

class FilterContainer implements FlareCallbackContainerInterface
{
    public const TABLE_NAME = 'tl_flare_filter';

    public function __construct(
        private readonly ConfiguredFilterFactory     $configuredFilterFactory,
        private readonly FlareCallbackManager        $callbacks,
        private readonly ListExecutionContextFactory $listExecutionContextFactory,
        private readonly ListSpecificationFactory    $listSpecificationFactory,
    ) {}

    /* ============================= *
     *  CALLBACK HANDLING            *
     * ============================= */
    // <editor-fold desc="Callback Handling">

    public function handleConfigOnLoad(?DataContainer $dc, string $target): void
    {
        [$filterModel, $listModel] = $this->getModelsFromDataContainer($dc);

        if (!$filterModel || !$listModel) {
            return;
        }

        $callbacks = $this->callbacks->getFilterCallbacks($filterModel->type, $target, lowPrioFirst: true);

        CallbackHelper::call($callbacks, [], [
            FilterModel::class => $filterModel,
            ListModel::class  => $listModel,
            DataContainer::class  => $dc,
        ]);
    }

    /**
     * @throws \RuntimeException
     * @throws FlareException
     */
    public function handleFieldOptions(?DataContainer $dc, string $target): array
    {
        [$filterModel, $listModel] = $this->getModelsFromDataContainer($dc);

        if (!$filterModel || !$listModel) {
            return [];
        }

        $callbacks = $this->callbacks->getFilterCallbacks($filterModel->type, $target);

        $configuredFilter = $this->configuredFilterFactory->create($filterModel);
        $listSpecification = $this->listSpecificationFactory->create($listModel);
        $context = $this->listExecutionContextFactory->create($listSpecification);
        $tables = $context->tableAliasRegistry->getTables();
        $targetTable = $context->tableAliasRegistry->getTable($filterModel->targetAlias) ?: $listModel->dc;

        return CallbackHelper::firstReturn($callbacks, [], [
            FilterModel::class => $filterModel,
            ListModel::class  => $listModel,
            DataContainer::class  => $dc,
            ConfiguredFilter::class => $configuredFilter,
            ListSpecification::class => $listSpecification,
            ListExecutionContext::class => $context,
            'tables' => $tables,
            'targetTable' => $targetTable,
        ]) ?? [];
    }

    /**
     * @throws \RuntimeException
     */
    public function handleLoadField(mixed $value, ?DataContainer $dc, string $target): mixed
    {
        return $this->handleValueCallback($value, $dc, $target);
    }

    /**
     * @throws \RuntimeException
     */
    public function handleSaveField(mixed $value, ?DataContainer $dc, string $target): mixed
    {
        return $this->handleValueCallback($value, $dc, $target);
    }

    /**
     * @throws \RuntimeException
     */
    public function handleValueCallback(mixed $value, ?DataContainer $dc, string $target): mixed
    {
        [$filterModel, $listModel] = $this->getModelsFromDataContainer($dc);

        if (!$filterModel || !$listModel) {
            return $value;
        }

        $callbacks = $this->callbacks->getFilterCallbacks($filterModel->type, $target);

        return CallbackHelper::firstReturn($callbacks, [$value], [
            FilterModel::class => $filterModel,
            ListModel::class  => $listModel,
            DataContainer::class  => $dc,
        ]) ?? $value;
    }

    /**
     * @param DataContainer|null $dc
     * @param bool               $ignoreType
     * @return array{FilterModel, ListModel}|array{null, null}
     * @mago-expect lint:no-empty-catch-clause It's fine if the models are not found.
     */
    public function getModelsFromDataContainer(?DataContainer $dc, bool $ignoreType = false): array
    {
        try
        {
            if (($id = $dc?->id)
                && ($filterModel = FilterModel::findByPk($id))
                && ($ignoreType || $filterModel->type)
                && ($listModel = $filterModel->getRelated('pid')))
            {
                if (!$listModel instanceof ListModel) {
                    return [null, null];
                }

                return [$filterModel, $listModel];
            }
        }
        catch (\Throwable) {}

        return [null, null];
    }

    // </editor-fold>
}
