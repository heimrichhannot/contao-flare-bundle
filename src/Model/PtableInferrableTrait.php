<?php

namespace HeimrichHannot\FlareBundle\Model;

use HeimrichHannot\FlareBundle\Util\PtableInferrer;

trait PtableInferrableTrait
{
    public function getInferFieldPid(): ?string
    {
        return $this->fieldPid ?: null;
    }

    public function getInferWhichPtable(): string
    {
        if ($this->whichPtable
            && \in_array($this->whichPtable, PtableInferrer::WHICH_PTABLE_OPTIONS, true))
        {
            return $this->whichPtable;
        }

        return PtableInferrer::WHICH_PTABLE_DEFAULT;
    }

    public function getInferFieldPtable(): ?string
    {
        return $this->fieldPtable ?: null;
    }

    public function getInferTablePtable(): ?string
    {
        return $this->tablePtable ?: null;
    }

    public function whichPtable_disableAutoOption(): void
    {
        $GLOBALS['TL_DCA'][self::getTable()]['fields']['whichPtable']['options'] = ['dynamic', 'static'];
        $GLOBALS['TL_DCA'][self::getTable()]['fields']['whichPtable']['default'] = ['dynamic'];

        if ($this->whichPtable === 'auto')
        {
            $this->whichPtable = 'dynamic';
            $this->save();
        }
    }
}