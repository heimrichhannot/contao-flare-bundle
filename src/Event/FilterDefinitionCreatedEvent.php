<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Specification\FilterDefinition;
use Symfony\Contracts\EventDispatcher\Event;

class FilterDefinitionCreatedEvent extends Event
{
    public function __construct(
        public FilterDefinition $filterDefinition,
    ) {}
}