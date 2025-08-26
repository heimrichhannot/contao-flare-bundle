<?php

namespace HeimrichHannot\FlareBundle\Manager;

use HeimrichHannot\FlareBundle\Registry\FlareCallbackRegistry;

class FlareCallbackManager
{
    public const PREFIX_FILTER = 'filter.';
    public const PREFIX_LIST = 'list.';

    public function __construct(
        private readonly FlareCallbackRegistry $registry,
    ) {}

    public function getListCallbacks(string $who, string $what, bool $lowPrioFirst = false): array
    {
        $namespace = self::PREFIX_LIST . $who;

        return $this->getCallbacks($namespace, $what, $lowPrioFirst);
    }

    public function getFilterCallbacks(string $who, string $what, bool $lowPrioFirst = false): array
    {
        $namespace = self::PREFIX_FILTER . $who;

        return $this->getCallbacks($namespace, $what, $lowPrioFirst);
    }

    private function getCallbacks(string $namespace, string $target, bool $lowPrioFirst = false): array
    {
        if (!$namespace || !$target) {
            return [];
        }

        $callbacks = $this->registry->getSorted($namespace, $target) ?: [];

        if ($lowPrioFirst) {
            $callbacks = \array_reverse($callbacks);
        }

        return $callbacks;
    }
}