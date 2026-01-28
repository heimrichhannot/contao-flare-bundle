<?php

namespace HeimrichHannot\FlareBundle\Manager;

use HeimrichHannot\FlareBundle\Contract\Config\ListItemProviderConfig;
use HeimrichHannot\FlareBundle\Contract\ListType\ListItemProviderContract;
use HeimrichHannot\FlareBundle\ListItemProvider\ListItemProvider;
use HeimrichHannot\FlareBundle\ListItemProvider\ListItemProviderInterface;
use HeimrichHannot\FlareBundle\Registry\ListTypeRegistry;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

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
     * @return ListItemProviderInterface The list item provider to use.
     */
    public function ofList(ListSpecification $spec): ListItemProviderInterface
    {
        $service = $this->listTypeRegistry->get($spec->type ?: null)?->getService();

        if ($service instanceof ListItemProviderContract)
        {
            return $service->getListItemProvider(new ListItemProviderConfig($spec))
                ?? $this->defaultItemProvider;
        }

        return $this->defaultItemProvider;
    }
}