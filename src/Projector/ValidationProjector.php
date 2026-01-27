<?php

namespace HeimrichHannot\FlareBundle\Projector;

use HeimrichHannot\FlareBundle\List\ListContext;
use HeimrichHannot\FlareBundle\List\ListDefinition;
use HeimrichHannot\FlareBundle\Projector\Projection\ProjectionInterface;

class ValidationProjector extends AbstractProjector
{
    public static function getContext(): string
    {
        return ListContext::VALIDATION;
    }

    protected function execute(ListContext $context, ListDefinition $listDefinition): ProjectionInterface
    {
        // TODO: Implement execute() method.
    }
}