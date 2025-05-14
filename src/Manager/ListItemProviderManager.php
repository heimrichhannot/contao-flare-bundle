<?php

namespace HeimrichHannot\FlareBundle\Manager;

use HeimrichHannot\FlareBundle\Contract\Config\ListItemProviderConfig;
use HeimrichHannot\FlareBundle\Contract\ListType\ListItemProviderContract;
use HeimrichHannot\FlareBundle\List\ListItemProvider;
use HeimrichHannot\FlareBundle\List\ListItemProviderInterface;
use HeimrichHannot\FlareBundle\List\ListTypeRegistry;
use HeimrichHannot\FlareBundle\Model\ListModel;

readonly class ListItemProviderManager
{
    public function __construct(
        private ListItemProvider $defaultItemProvider,
        private ListTypeRegistry $listTypeRegistry,
    ) {}

    /**
     * Get the list item provider for a given list model.
     * If the list model implements the {@see ListItemProviderContract} interface, it will be used to retrieve the
     * list item provider. Otherwise, the default item provider {@see ListItemProvider} will be used.
     *
     * @param ListModel $listModel The list model.
     *
     * @return ListItemProviderInterface The list item provider to use.
     */
    public function ofListModel(ListModel $listModel): ListItemProviderInterface
    {
        $service = $this->listTypeRegistry->get($listModel->type ?: null)?->getService();

        if ($service instanceof ListItemProviderContract)
        {
            return $service->getListItemProvider(new ListItemProviderConfig($listModel))
                ?? $this->defaultItemProvider;
        }

        return $this->defaultItemProvider;
    }
}