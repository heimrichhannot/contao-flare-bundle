<?php

namespace HeimrichHannot\FlareBundle\Util;

interface PtableInferrable
{
    public function getInferFieldPid(): ?string;
    public function getInferWhichPtable(): string;
    public function getInferFieldPtable(): ?string;
    public function getInferTablePtable(): ?string;
}