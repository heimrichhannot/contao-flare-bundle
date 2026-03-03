<?php

namespace HeimrichHannot\FlareBundle\Factory;

use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder;
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