<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\Query\SqlQueryStruct;
use HeimrichHannot\FlareBundle\Query\TableAliasRegistry;
use Symfony\Contracts\EventDispatcher\Event;

class QueryBaseInitializedEvent extends Event
{
    public function __construct(
        public readonly ListSpec           $list,
        public readonly TableAliasRegistry $registry,
        public readonly SqlQueryStruct     $struct,
    ) {}
}
