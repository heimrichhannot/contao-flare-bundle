<?php

namespace HeimrichHannot\FlareBundle\Projector;

use HeimrichHannot\FlareBundle\Context\ContextConfigInterface;
use HeimrichHannot\FlareBundle\View\ExportView;
use HeimrichHannot\FlareBundle\View\ViewInterface;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

/**
 * @implements ProjectorInterface<ExportView>
 */
class ExportProjector extends AbstractProjector
{
    public function supports(ContextConfigInterface $config): bool
    {
        return false;
    }

    public function project(ListSpecification $spec, ContextConfigInterface $config): ViewInterface
    {
        throw new \RuntimeException('Not implemented.');
    }
}