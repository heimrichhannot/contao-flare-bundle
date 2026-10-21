<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Model;

use HeimrichHannot\FlareBundle\InferPtable\PtableInferrer;

trait PtableInferrableTrait
{
    /**
     * The column holding the given inference setting (`fieldPid`, `whichPtable`, `fieldPtable`, `tablePtable`);
     * models whose columns carry a prefix override this.
     */
    protected static function getPtableInferColumn(string $name): string
    {
        return $name;
    }

    public function getInferFieldPid(): ?string
    {
        return $this->{static::getPtableInferColumn('fieldPid')} ?: null;
    }

    public function getInferWhichPtable(): string
    {
        $whichPtable = $this->{static::getPtableInferColumn('whichPtable')};

        if ($whichPtable && \in_array($whichPtable, PtableInferrer::WHICH_PTABLE_OPTIONS, true)) {
            return $whichPtable;
        }

        return PtableInferrer::WHICH_PTABLE_DEFAULT;
    }

    public function getInferFieldPtable(): ?string
    {
        return $this->{static::getPtableInferColumn('fieldPtable')} ?: null;
    }

    public function getInferTablePtable(): ?string
    {
        return $this->{static::getPtableInferColumn('tablePtable')} ?: null;
    }

    public function whichPtable_disableAutoOption(): void
    {
        $column = static::getPtableInferColumn('whichPtable');

        $GLOBALS['TL_DCA'][self::getTable()]['fields'][$column]['options'] = ['dynamic', 'static'];
        $GLOBALS['TL_DCA'][self::getTable()]['fields'][$column]['default'] = ['dynamic'];

        if ($this->{$column} === 'auto')
        {
            $this->{$column} = 'dynamic';
            $this->save();
        }
    }
}
