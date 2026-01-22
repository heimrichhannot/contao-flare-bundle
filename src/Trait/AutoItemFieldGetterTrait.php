<?php

namespace HeimrichHannot\FlareBundle\Trait;

use HeimrichHannot\FlareBundle\Util\DcaHelper;

trait AutoItemFieldGetterTrait
{
    public function getAutoItemField(): string
    {
        return $this->fieldAutoItem ?: DcaHelper::tryGetColumnName($this->dc, 'alias', 'id');
    }
}