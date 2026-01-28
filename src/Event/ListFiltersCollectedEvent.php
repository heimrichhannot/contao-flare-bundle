<?php

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Filter\FilterDefinitionCollection;
use HeimrichHannot\FlareBundle\Specification\DataSource\ListDataSourceInterface;
use Symfony\Contracts\EventDispatcher\Event;

class ListFiltersCollectedEvent extends Event
{
    public function __construct(
        public FilterDefinitionCollection       $filters,
        public readonly ListDataSourceInterface $dataSource,
    ) {}
}