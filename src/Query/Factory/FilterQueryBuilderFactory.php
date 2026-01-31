<?php

namespace HeimrichHannot\FlareBundle\Query\Factory;

use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Query\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Util\SqlHelper;

readonly class FilterQueryBuilderFactory
{
    public function __construct(
        private Connection $connection,
        private SqlHelper  $sqlHelper,
    ) {}

    public function create(string $alias): FilterQueryBuilder
    {
        return new FilterQueryBuilder(
            connection: $this->connection,
            sqlHelper: $this->sqlHelper,
            alias: $alias
        );
    }
}