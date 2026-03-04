<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Specification;

use HeimrichHannot\FlareBundle\Util\DcaHelper;

trait AutoItemFieldGetterTrait
{
    public function getAutoItemField(): string
    {
        return $this->fieldAutoItem ?: DcaHelper::tryGetColumnName($this->dc, 'alias', 'id');
    }
}