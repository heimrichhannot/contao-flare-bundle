<?php

namespace HeimrichHannot\FlareBundle\Util;

use Contao\Controller;
use HeimrichHannot\FlareBundle\Exception\InferenceException;
use HeimrichHannot\FlareBundle\Model\ListModel;

class PtableInferrer
{
    public const WHICH_PTABLE_AUTO = 'auto';
    public const WHICH_PTABLE_STATIC = 'static';
    public const WHICH_PTABLE_DYNAMIC = 'dynamic';
    public const WHICH_PTABLE_DEFAULT = self::WHICH_PTABLE_AUTO;
    public const WHICH_PTABLE_OPTIONS = [
        self::WHICH_PTABLE_AUTO,
        self::WHICH_PTABLE_STATIC,
        self::WHICH_PTABLE_DYNAMIC,
    ];

    protected bool $autoInferable = true;
    protected bool $autoDynamicPtable = false;
    protected string $entityTable;
    protected bool $inferred = false;
    protected ?string $inferredPtable;

    public function __construct(
        protected PtableInferrableInterface $inferrable,
        protected ListModel        $listModel,
    ) {
        $this->entityTable = $this->listModel->dc;
    }

    public function getInferrable(): PtableInferrableInterface
    {
        return $this->inferrable;
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

    public function getDCA(): ?array
    {
        if (!$this->entityTable) {
            return null;
        }

        Controller::loadDataContainer($this->entityTable);

        return $GLOBALS['TL_DCA'][$this->entityTable] ?? null;
    }

    public function getDcaMainPtable(): ?string
    {
        if (!$entityDca = $this->getDCA()) {
            return null;
        }

        $ptable = $entityDca['config']['ptable'] ?? null;

        if ($ptable && \is_string($ptable)) {
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

        $fieldPid = $this->inferrable->getInferFieldPid() ?: 'pid';

        if ($fieldPid === 'pid' && \is_string($ptable = $entityDca['config']['ptable'] ?? null))
            // the parent table is defined in the data container
            //   => default contao behavior with the parent id field being "pid"
        {
            $this->inferredPtable = $ptable;
            $this->autoInferable = true;
            $this->autoDynamicPtable = false;

            return $this->inferredPtable;
        }

        if (\is_string($foreignKey = $entityDca['fields'][$fieldPid]['foreignKey'] ?? null))
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
        if ($alwaysInfer) {
            $this->infer();
        }

        return match ($this->inferrable->getInferWhichPtable()) {
            self::WHICH_PTABLE_DYNAMIC => null,
            self::WHICH_PTABLE_STATIC => $this->inferrable->getInferTablePtable(),
            default => $this->infer(),
        };
    }

    public function getPidField(): string
    {
        return $this->inferrable->getInferFieldPid() ?: 'pid';
    }

    /**
     * @throws InferenceException
     */
    public function tryGetDynamicPtableField(): string|null
    {
        if ($this->inferrable->getInferWhichPtable() === self::WHICH_PTABLE_STATIC)
        {
            return null;
        }

        if ($this->inferrable->getInferWhichPtable() === self::WHICH_PTABLE_DYNAMIC)
        {
            return $this->inferrable->getInferFieldPtable() ?: 'ptable';
        }

        $this->infer();

        if ($this->autoDynamicPtable)
        {
            return 'ptable';
        }

        return null;
    }
}