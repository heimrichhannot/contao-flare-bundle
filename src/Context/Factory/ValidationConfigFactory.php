<?php

namespace HeimrichHannot\FlareBundle\Context\Factory;

use HeimrichHannot\FlareBundle\Context\ValidationConfig;
use HeimrichHannot\FlareBundle\View\InteractiveView;

class ValidationConfigFactory
{
    public function createFromInteractiveProjection(InteractiveView $interactiveProjection): ValidationConfig
    {
        return new ValidationConfig(
            entryCache: function () use ($interactiveProjection): ?array {
                return $interactiveProjection->isEntriesLoaded()
                    ? $interactiveProjection->getEntries()
                    : null;
            },
        );
    }
}