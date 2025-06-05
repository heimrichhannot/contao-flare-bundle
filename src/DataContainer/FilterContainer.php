<?php

namespace HeimrichHannot\FlareBundle\DataContainer;

use Contao\DataContainer;
use HeimrichHannot\FlareBundle\FlareCallback\FlareCallbackContainerInterface;
use HeimrichHannot\FlareBundle\FlareCallback\FlareCallbackRegistry;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Util\CallbackHelper;

class FilterContainer implements FlareCallbackContainerInterface
{
    public const TABLE_NAME = 'tl_flare_filter';
    public const CALLBACK_PREFIX = 'filter';

    public function __construct(
        private readonly FlareCallbackRegistry $callbackRegistry,
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

        $namespace = static::CALLBACK_PREFIX . '.' . $filterModel->type;

        $callbacks = $this->callbackRegistry->getSorted($namespace, $target) ?? [];
        $callbacks = \array_reverse($callbacks);

        CallbackHelper::call($callbacks, [], [
            FilterModel::class => $filterModel,
            ListModel::class  => $listModel,
            DataContainer::class  => $dc,
        ]);
    }

    /**
     * @throws \RuntimeException
     */
    public function handleFieldOptions(?DataContainer $dc, string $target): array
    {
        [$filterModel, $listModel] = $this->getModelsFromDataContainer($dc);

        if (!$filterModel || !$listModel) {
            return [];
        }

        $namespace = static::CALLBACK_PREFIX . '.' . $filterModel->type;

        $callbacks = $this->callbackRegistry->getSorted($namespace, $target) ?? [];

        return CallbackHelper::firstReturn($callbacks, [], [
            FilterModel::class => $filterModel,
            ListModel::class  => $listModel,
            DataContainer::class  => $dc,
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

        $namespace =  static::CALLBACK_PREFIX . '.' . $filterModel->type;

        $callbacks = $this->callbackRegistry->getSorted($namespace, $target) ?? [];

        return CallbackHelper::firstReturn($callbacks, [$value], [
            FilterModel::class => $filterModel,
            ListModel::class  => $listModel,
            DataContainer::class  => $dc,
        ]) ?? $value;
    }

    /**
     * @param DataContainer|null $dc
     * @return array{FilterModel, ListModel}|array{null, null}
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
                return [$filterModel, $listModel];
            }
        }
        catch (\Throwable) {}

        return [null, null];
    }

    // </editor-fold>
}