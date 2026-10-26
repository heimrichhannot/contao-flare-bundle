<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\List\ListSpecBuilder;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched while a list is being built, after the list driver's buildList() hook and before the
 * config is resolved. Listeners may add filters or config overrides — also per type via
 * the named event `flare.list_driver.{type}.build_list`.
 */
class ListDriverBuildListEvent extends Event
{
    public function __construct(
        public readonly ListSpecBuilder $builder,
    ) {}
}
