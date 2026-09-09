<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Util;

final class EntryCache
{
    private array $cache = [];

    public function __construct(
        public readonly string $table,
    ) {}

    public function add(int|string $key, mixed $value): self
    {
        $this->cache[$key] = $value;

        return $this;
    }

    public function addMany(array $entries): self
    {
        foreach ($entries as $key => $value) {
            $this->add($key, $value);
        }

        return $this;
    }

    public function remove(int|string $key): self
    {
        unset($this->cache[$key]);

        return $this;
    }

    public function get(int|string $key)
    {
        return $this->cache[$key] ?? null;
    }

    public function has(int|string $key): bool
    {
        return \array_key_exists($key, $this->cache);
    }
}
