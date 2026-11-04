<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Projector;

use HeimrichHannot\FlareBundle\Engine\Intent\IntentInterface;
use HeimrichHannot\FlareBundle\Engine\View\ExportView;
use HeimrichHannot\FlareBundle\Engine\View\ViewInterface;
use HeimrichHannot\FlareBundle\List\ListSpec;

/**
 * @implements ProjectorInterface<ExportView>
 */
class ExportProjector extends AbstractProjector
{
    public function supports(ListSpec $list, IntentInterface $intent): bool
    {
        return false;
    }

    public function project(ListSpec $list, IntentInterface $intent): ViewInterface
    {
        throw new \RuntimeException('Not implemented.');
    }
}
