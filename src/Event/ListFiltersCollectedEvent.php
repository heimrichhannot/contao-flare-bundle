<?php

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Filter\FilterDefinitionCollection;
use HeimrichHannot\FlareBundle\List\ListDataSource;
use Symfony\Contracts\EventDispatcher\Event;

class ListFiltersCollectedEvent extends Event
{
    public function __construct(
        public FilterDefinitionCollection $filters,
        public readonly ListDataSource    $dataSource,
    ) {}
}