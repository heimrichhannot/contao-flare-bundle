<?php

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Query\ListQueryBuilder;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use Symfony\Contracts\EventDispatcher\Event;

class ListQueryPrepareEvent extends Event
{
    public function __construct(
        public readonly ListSpecification $listDefinition,
        private ListQueryBuilder          $listQueryBuilder,
    ) {}

    public function getListQueryBuilder(): ListQueryBuilder
    {
        return $this->listQueryBuilder;
    }

    public function setListQueryBuilder(ListQueryBuilder $listQueryBuilder): void
    {
        $this->listQueryBuilder = $listQueryBuilder;
    }
}