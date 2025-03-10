<?php

namespace HeimrichHannot\FlareBundle\Util;

use Contao\Controller;
use HeimrichHannot\FlareBundle\Exception\InferenceException;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;

class PtableInferrer
{
    protected bool $autoInferable = true;
    protected bool $autoDynamicPtable = false;
    protected string $entityTable;
    protected bool $inferred = false;
    protected ?string $inferredPtable;

    public function __construct(
        protected FilterModel $filterModel,
        protected ListModel $listModel
    ) {
        $this->entityTable = $this->listModel->dc;
    }

    public function getFilterModel(): FilterModel
    {
        return $this->filterModel;
    }

    public function getListModel(): ListModel
    {
        return $this->listModel;
    }

    public function isAutoInferable(): bool
    {
        return $this->autoInferable;
    }

    public function isAutoDynamicPtable(): bool
    {
        return $this->autoDynamicPtable;
    }

    public function getEntityTable(): string
    {
        return $this->entityTable;
    }

    public function getDCA()
    {
        Controller::loadDataContainer($this->entityTable);

        return $GLOBALS['TL_DCA'][$this->entityTable] ?? null;
    }

    public function getDcaMainPtable(): ?string
    {
        if (!$entityDca = $this->getDCA()) {
            return null;
        }

        $ptable = $entityDca['config']['ptable'] ?? null;

        if (!empty($ptable) && \is_string($ptable)) {
            return $ptable;
        }

        return null;
    }

    public function isDcaDynamicPtable(): ?string
    {
        if (!$entityDca = $this->getDCA()) {
            return null;
        }

        return $entityDca['config']['dynamicPtable'] ?? null;
    }

    /**
     * @throws InferenceException
     */
    public function infer(): ?string
    {
        if ($this->inferred) {
            return $this->inferredPtable ?? null;
        }

        if (!$entityDca = $this->getDCA()) {
            throw new InferenceException('No data container array found for ' . $this->entityTable);
        }

        $this->inferred = true;

        if ($this->filterModel->fieldPid === 'pid' && \is_string($ptable = $entityDca['config']['ptable'] ?? null))
            // the parent table is defined in the data container
        {
            $this->inferredPtable = $ptable;
            $this->autoInferable = true;
            $this->autoDynamicPtable = false;

            return $this->inferredPtable;
        }

        if (\is_string($foreignKey = $entityDca['fields'][$this->filterModel->fieldPid]['foreignKey'] ?? null))
            // the parent table is defined in the field's foreign key
        {
            [$ptable, $field] = \explode('.', $foreignKey);
            $this->inferredPtable = $ptable;
            $this->autoInferable = true;
            $this->autoDynamicPtable = false;

            return $this->inferredPtable;
        }

        $this->inferredPtable = null;
        $this->autoInferable = false;
        $this->autoDynamicPtable = (bool) ($entityDca['config']['dynamicPtable'] ?? false);

        return $this->inferredPtable;
    }

    /**
     * Considers all possible cases and returns the inferred or user-defined ptable as explicitly as possible.
     *
     * @throws InferenceException
     */
    public function explicit(bool $alwaysInfer = false): ?string
    {
        if ($alwaysInfer) $this->infer();

        return match ($this->filterModel->whichPtable) {
            'dynamic' => null,
            'static' => $this->filterModel->tablePtable ?? null,
            default => $this->infer(),
        };
    }

    /**
     * @throws InferenceException
     */
    public function tryGetDynamicPtableField(): string|null
    {
        if ($this->filterModel->whichPtable === 'static')
        {
            return null;
        }

        if ($this->filterModel->whichPtable === 'dynamic')
        {
            return $this->filterModel->fieldPtable ?: 'ptable';
        }

        $this->infer();

        if ($this->autoDynamicPtable)
        {
            return 'ptable';
        }

        return null;
    }
}