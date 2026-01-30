<?php

namespace HeimrichHannot\FlareBundle\Engine\Projector;

use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\View\ExportView;
use HeimrichHannot\FlareBundle\Engine\View\ViewInterface;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

/**
 * @implements ProjectorInterface<ExportView>
 */
class ExportProjector extends AbstractProjector
{
    public function supports(ContextInterface $config): bool
    {
        return false;
    }

    public function project(ListSpecification $spec, ContextInterface $config): ViewInterface
    {
        throw new \RuntimeException('Not implemented.');
    }
}