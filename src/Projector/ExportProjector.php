<?php

namespace HeimrichHannot\FlareBundle\Projector;

use HeimrichHannot\FlareBundle\List\ListContext;
use HeimrichHannot\FlareBundle\List\ListDefinition;
use HeimrichHannot\FlareBundle\Projector\Projection\ProjectionInterface;

class ExportProjector extends AbstractProjector
{
    public static function getContext(): string
    {
        return ListContext::EXPORT;
    }

    protected function execute(ListContext $context, ListDefinition $listDefinition): ProjectionInterface
    {
        // TODO: Implement execute() method.
    }
}