<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\InferPtable;

final readonly class PtableInferrable implements PtableInferrableInterface
{
    public function __construct(
        private string $fieldPid,
        private string $whichPtable,
        private string $fieldPtable,
        private string $tablePtable,
    ) {}

    public function getInferFieldPid(): string
    {
        return $this->fieldPid;
    }

    public function getInferWhichPtable(): string
    {
        return $this->whichPtable;
    }

    public function getInferFieldPtable(): string
    {
        return $this->fieldPtable;
    }

    public function getInferTablePtable(): string
    {
        return $this->tablePtable;
    }

    public function with(
        ?string $fieldPid = null,
        ?string $whichPtable = null,
        ?string $fieldPtable = null,
        ?string $tablePtable = null,
    ): self {
        return new self(
            fieldPid: $fieldPid ?? $this->fieldPid,
            whichPtable: $whichPtable ?? $this->whichPtable,
            fieldPtable: $fieldPtable ?? $this->fieldPtable,
            tablePtable: $tablePtable ?? $this->tablePtable,
        );
    }
}