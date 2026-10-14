<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\List\ListBuilder;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched while a list is being built, after the type's buildList() hook and before the
 * config is resolved. Listeners may add filters or config overrides — also per type via
 * the named event `flare.list.{type}.build`.
 */
class ListBuildEvent extends Event
{
    public function __construct(
        public readonly ListBuilder $builder,
    ) {}
}
