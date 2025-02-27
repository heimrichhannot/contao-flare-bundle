<?php /** @noinspection PhpRedundantMethodOverrideInspection */

namespace HeimrichHannot\FlareBundle\List;

use HeimrichHannot\FlareBundle\DependencyInjection\Registry\AbstractRegistry;

class ListTypeRegistry extends AbstractRegistry
{
    public function getConfigClass(): string
    {
        return ListTypeConfig::class;
    }

    public function get(string $alias): ?ListTypeConfig
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return parent::get($alias);
    }

    /**
     * @return array<string, ListTypeConfig>
     */
    public function all(): array
    {
        return parent::all();
    }
}