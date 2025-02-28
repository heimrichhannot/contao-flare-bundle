<?php /** @noinspection PhpRedundantMethodOverrideInspection */

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\DependencyInjection\Registry\AbstractRegistry;

class FilterElementRegistry extends AbstractRegistry
{
    public function getConfigClass(): string
    {
        return FilterElementConfig::class;
    }

    public function get(?string $alias): ?FilterElementConfig
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return parent::get($alias);
    }

    /**
     * @return array<string, FilterElementConfig>
     */
    public function all(): array
    {
        return parent::all();
    }
}