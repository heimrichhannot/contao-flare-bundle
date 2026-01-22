<?php

namespace HeimrichHannot\FlareBundle\Manager;

use HeimrichHannot\FlareBundle\Contract\Config\ListItemProviderConfig;
use HeimrichHannot\FlareBundle\Contract\ListType\ListItemProviderContract;
use HeimrichHannot\FlareBundle\List\ListDefinition;
use HeimrichHannot\FlareBundle\ListItemProvider\ListItemProvider;
use HeimrichHannot\FlareBundle\ListItemProvider\ListItemProviderInterface;
use HeimrichHannot\FlareBundle\Registry\ListTypeRegistry;
use HeimrichHannot\FlareBundle\Model\ListModel;

readonly class ListItemProviderManager
{
    public function __construct(
        private ListItemProviderInterface $defaultItemProvider,
        private ListTypeRegistry $listTypeRegistry,
    ) {}

    /**
     * Get the list item provider for a given list model.
     * If the list model implements the {@see ListItemProviderContract} interface, it will be used to retrieve the
     * list item provider. Otherwise, the default item provider {@see ListItemProvider} will be used.
     *
     * @param ListModel $list The list model.
     *
     * @return ListItemProviderInterface The list item provider to use.
     */
    public function ofList(ListDefinition $list): ListItemProviderInterface
    {
        $service = $this->listTypeRegistry->get($list->type ?: null)?->getService();

        if ($service instanceof ListItemProviderContract)
        {
            return $service->getListItemProvider(new ListItemProviderConfig($list))
                ?? $this->defaultItemProvider;
        }

        return $this->defaultItemProvider;
    }
}