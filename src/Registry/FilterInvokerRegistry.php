<?php

namespace HeimrichHannot\FlareBundle\Registry;

class FilterInvokerRegistry
{
    private array $invokers = [];

    public function add(string $filterType, ?string $context, string $serviceId, string $method, int $priority): void
    {
        $this->invokers[$filterType][$context ?? 'default'][$priority][] = [
            'serviceId' => $serviceId,
            'method' => $method
        ];
    }

    public function find(string $filterType, string $context): ?array
    {
        $invokers = $this->invokers[$filterType][$context] ?? null;

        if ($invokers === null && $context !== 'default') {
            $invokers = $this->invokers[$filterType]['default'] ?? null;
        }

        if ($invokers === null) {
            return null;
        }

        \krsort($invokers);

        return \current($invokers)[0] ?? null;
    }
}