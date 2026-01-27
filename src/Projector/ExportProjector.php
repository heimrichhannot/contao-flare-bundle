<?php

namespace HeimrichHannot\FlareBundle\Projector;

use HeimrichHannot\FlareBundle\List\ListContext;
use HeimrichHannot\FlareBundle\List\ListDefinition;
use HeimrichHannot\FlareBundle\Projector\Projection\ExportProjection;

/**
 * @implements ProjectorInterface<ExportProjection>
 */
class ExportProjector extends AbstractProjector
{
    public static function getContext(): string
    {
        return ListContext::EXPORT;
    }

    public static function getProjectionClass(): string
    {
        return ExportProjection::class;
    }

    protected function execute(ListContext $listContext, ListDefinition $listDefinition): ExportProjection
    {
        // TODO: Implement execute() method.
    }
}