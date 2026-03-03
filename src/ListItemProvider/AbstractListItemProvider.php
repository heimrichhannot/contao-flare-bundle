<?php

namespace HeimrichHannot\FlareBundle\ListItemProvider;

use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

/**
 * Abstract class for list item providers.
 */
abstract class AbstractListItemProvider implements ListItemProviderInterface, ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;
}