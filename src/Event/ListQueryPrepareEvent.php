<?php

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\List\ListDefinition;
use HeimrichHannot\FlareBundle\List\ListQueryBuilder;
use Symfony\Contracts\EventDispatcher\Event;

class ListQueryPrepareEvent extends Event
{
    public function __construct(
        public readonly ListDefinition $listDefinition,
        private ListQueryBuilder        $listQueryBuilder,
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