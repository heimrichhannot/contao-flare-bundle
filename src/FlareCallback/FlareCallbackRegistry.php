<?php

namespace HeimrichHannot\FlareBundle\FlareCallback;

use HeimrichHannot\FlareBundle\DependencyInjection\Registry\AbstractPriorityRegistry;

class FlareCallbackRegistry extends AbstractPriorityRegistry
{
    public function getConfigClass(): string
    {
        return FlareCallbackConfig::class;
    }

    /**
     * @return FlareCallbackConfig[]|null
     */
    public function getSorted(string $namespace, string $key): ?array
    {
        return parent::getSorted($namespace, $key);
    }
}