<?php

namespace HeimrichHannot\FlareBundle\FlareCallback;

use HeimrichHannot\FlareBundle\DependencyInjection\Registry\AbstractPriorityRegistry;

/**
 * {@inheritdoc}
 *
 * @internal For internal use only. API might change without notice.
 *
 * @template namespace of string
 * @template key of string
 * @template prio of int
 */
class FlareCallbackRegistry extends AbstractPriorityRegistry
{
    public function getConfigClass(): string
    {
        return FlareCallbackConfig::class;
    }

    /**
     * @return array<prio, FlareCallbackConfig[]>|array<key, array<prio, FlareCallbackConfig[]>>|null
     */
    public function get(string $namespace, string $key = null): ?array
    {
        return parent::get($namespace, $key);
    }

    /**
     * @return FlareCallbackConfig[]|null
     */
    public function getSorted(string $namespace, string $key): ?array
    {
        return parent::getSorted($namespace, $key);
    }
}