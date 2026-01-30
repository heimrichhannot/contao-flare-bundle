<?php

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use Symfony\Contracts\EventDispatcher\Event;

class ListSpecificationCreatedEvent extends Event
{
    public function __construct(
        public ListSpecification $listSpecification,
    ) {}
}