<?php

namespace HeimrichHannot\FlareBundle\InferPtable;

interface PtableInferrableInterface
{
    public function getInferFieldPid(): ?string;

    public function getInferWhichPtable(): string;

    public function getInferFieldPtable(): ?string;

    public function getInferTablePtable(): ?string;
}