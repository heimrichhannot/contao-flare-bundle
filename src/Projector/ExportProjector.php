<?php

namespace HeimrichHannot\FlareBundle\Projector;

use HeimrichHannot\FlareBundle\List\ListContext;
use HeimrichHannot\FlareBundle\List\ListDefinition;

class ExportProjector extends AbstractProjector
{
    public static function getContext(): string
    {
        return 'export';
    }

    protected function execute(ListContext $context, ListDefinition $listDefinition): mixed
    {
        // TODO: Implement execute() method.
    }
}