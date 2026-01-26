<?php

namespace HeimrichHannot\FlareBundle\Projector;

use HeimrichHannot\FlareBundle\List\ListContext;
use HeimrichHannot\FlareBundle\List\ListDefinition;

class ValidationProjector extends AbstractProjector
{
    public static function getContext(): string
    {
        return 'validation';
    }

    protected function execute(ListContext $context, ListDefinition $listDefinition): mixed
    {
        // TODO: Implement execute() method.
    }
}