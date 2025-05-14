<?php

namespace HeimrichHannot\FlareBundle\Contract\ListType;

use HeimrichHannot\FlareBundle\Contract\Config\ListItemProviderConfig;
use HeimrichHannot\FlareBundle\List\ListItemProviderInterface;

/**
 * Implement this interface to specify a custom list item provider for a list type.
 */
interface ListItemProviderContract
{
    /**
     * Returns a custom list item provider for the given list model.
     * If null is returned, the default list item provider will be used.
     */
    public function getListItemProvider(ListItemProviderConfig $config): ?ListItemProviderInterface;
}