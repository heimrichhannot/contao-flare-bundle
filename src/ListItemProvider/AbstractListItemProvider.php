<?php

namespace HeimrichHannot\FlareBundle\ListItemProvider;

use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

/**
 * Abstract class for list item providers.
 *
 * This class provides a default implementation for {@see ListItemProvider::fetchEntry()} that caches the results.
 */
abstract class AbstractListItemProvider implements ListItemProviderInterface, ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;
}