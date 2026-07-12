<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Query;

trait QueryHelperTrait
{
    public function makeJoinOn(string $alias1, string $col1, string $alias2, string $col2): string
    {
        return sprintf('%s.%s = %s.%s', $alias1, $col1, $alias2, $col2);
    }
}