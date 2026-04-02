<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Integration\CodefogTags\Registry;

class CfgTagsManagersRegistry
{
    private array $managers;

    public function set(string $table, string $field, string $serviceId): void
    {
        $this->managers[$table][$field] = $serviceId;
    }

    public function findServiceId(string $table, string $field): ?string
    {
        return $this->managers[$table][$field] ?? null;
    }

    public function fieldsOf(string $table): array
    {
        return \array_keys($this->managers[$table] ?? []);
    }
}