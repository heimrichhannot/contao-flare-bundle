<?php

declare(strict_types=1);

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
    public function supports(ListSpecification $list, ContextInterface $context): bool
    {
        return false;
    }

    public function project(ListSpecification $list, ContextInterface $context): ViewInterface
    {
        throw new \RuntimeException('Not implemented.');
    }
}