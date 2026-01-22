<?php

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Filter\FilterDefinitionCollection;
use HeimrichHannot\FlareBundle\Model\ListModel;
use Symfony\Contracts\EventDispatcher\Event;

class ListFiltersCollectedEvent extends Event
{
    public function __construct(
        public FilterDefinitionCollection $filters,
        public readonly ListModel         $listModel,
    ) {}
}