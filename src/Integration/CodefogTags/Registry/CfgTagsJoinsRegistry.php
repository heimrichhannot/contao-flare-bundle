<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Integration\CodefogTags\Registry;

use HeimrichHannot\FlareBundle\Integration\CodefogTags\CfgTagsJoinAttribute;

class CfgTagsJoinsRegistry
{
    /**
     * @var array<string, CfgTagsJoinAttribute>
     */
    private array $entries = [];

    public function register(string $alias, CfgTagsJoinAttribute $config): void
    {
        $this->entries[$alias] = $config;
    }

    public function get(string $alias): ?CfgTagsJoinAttribute
    {
        return $this->entries[$alias] ?? null;
    }

    /**
     * @return array<string, CfgTagsJoinAttribute>
     */
    public function all(): array
    {
        return $this->entries;
    }
}
