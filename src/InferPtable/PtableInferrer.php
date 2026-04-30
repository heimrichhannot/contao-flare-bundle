<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\InferPtable;

use Contao\Controller;
use HeimrichHannot\FlareBundle\Exception\InferenceException;

final class PtableInferrer
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

    private bool $autoInferable = true;
    private bool $autoDynamicPtable = false;
    private bool $inferred = false;
    private ?string $inferredPtable;

    public function __construct(
        private readonly PtableInferrableInterface $inferrable,
        private readonly string                    $entityTable,
    ) {}

    public function getInferrable(): PtableInferrableInterface
    {
        return $this->inferrable;
    }

    public function getEntityTable(): string
    {
        return $this->entityTable;
    }

    public function isAutoInferable(): bool
    {
        if (!$this->inferred) {
            $this->infer();
        }

        return $this->autoInferable;
    }

    public function isAutoDynamicPtable(): bool
    {
        if (!$this->inferred) {
            $this->infer();
        }

        return $this->autoDynamicPtable;
    }

    public function getInferredPtable(): ?string
    {
        if (!$this->inferred) {
            $this->infer();
        }

        return $this->inferredPtable ?? null;
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

    public function isDcaDynamicPtable(): bool
    {
        if (!$entityDca = $this->getDCA()) {
            return false;
        }

        return $entityDca['config']['dynamicPtable'] ?? false;
    }

    /**
     * @throws InferenceException
     * @deprecated Use {@see self::getInferredPtable()} instead. Return type will change to void. Visibility will change to private.
     */
    #[\ReturnTypeWillChange]
    public function infer(): ?string
    {
        if ($this->inferred) {
            return $this->inferredPtable ?? null;
        }

        $this->inferred = true;
        $this->autoInferable = true;
        $this->autoDynamicPtable = false;

        $whichPtable = $this->inferrable->getInferWhichPtable();

        if ($whichPtable === self::WHICH_PTABLE_STATIC)
        {
            $this->inferredPtable = $this->inferrable->getInferTablePtable();

            return $this->inferredPtable;
        }

        if (!$entityDca = $this->getDCA()) {
            throw new InferenceException('No data container array found for ' . $this->entityTable);
        }

        $fieldPid = $this->inferrable->getInferFieldPid() ?: 'pid';

        if ($fieldPid === 'pid' && \is_string($ptable = $entityDca['config']['ptable'] ?? null))
            // the parent table is defined in the data container
            //   => default contao behavior with the parent id field being "pid"
        {
            $this->inferredPtable = $ptable;

            return $this->inferredPtable;
        }

        if (\is_string($foreignKey = $entityDca['fields'][$fieldPid]['foreignKey'] ?? null))
            // the parent table is defined in the field's foreign key
        {
            [$ptable, $field] = \explode('.', $foreignKey);
            $this->inferredPtable = $ptable;

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
     * @deprecated Use {@see self::getInferredPtable()} instead. Removal pending for v0.2.
     */
    public function explicit(bool $alwaysInfer = false): ?string
    {
        return $this->getInferredPtable();
    }

    public function getPidField(): string
    {
        return $this->inferrable->getInferFieldPid() ?: 'pid';
    }

    /**
     * @throws InferenceException
     */
    public function tryGetDynamicPtableField(): ?string
    {
        if ($this->inferrable->getInferWhichPtable() === self::WHICH_PTABLE_STATIC)
        {
            return null;
        }

        if ($this->inferrable->getInferWhichPtable() === self::WHICH_PTABLE_DYNAMIC)
        {
            return $this->inferrable->getInferFieldPtable() ?: 'ptable';
        }

        if ($this->isAutoDynamicPtable())
        {
            return 'ptable';
        }

        return null;
    }
}