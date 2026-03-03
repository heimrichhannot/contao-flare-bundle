<?php

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Query\FilterQuery;
use HeimrichHannot\FlareBundle\Query\ListQueryConfig;
use HeimrichHannot\FlareBundle\Query\SqlQueryStruct;
use HeimrichHannot\FlareBundle\Query\TableAliasRegistry;
use Symfony\Contracts\EventDispatcher\Event;

class ModifyListQueryStructEvent extends Event
{
    /**
     * @param FilterQuery[] $filterQueries
     * @param ListQueryConfig $config
     * @param TableAliasRegistry $tableAliasRegistry
     * @param SqlQueryStruct $queryStruct
     */
    public function __construct(
        public readonly array              $filterQueries,
        public readonly ListQueryConfig    $config,
        public readonly TableAliasRegistry $tableAliasRegistry,
        public SqlQueryStruct              $queryStruct,
    ) {}
}