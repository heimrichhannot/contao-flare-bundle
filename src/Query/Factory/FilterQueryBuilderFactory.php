<?php

namespace HeimrichHannot\FlareBundle\Query\Factory;

use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Query\FilterQueryBuilder;

readonly class FilterQueryBuilderFactory
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function create(string $alias): FilterQueryBuilder
    {
        return new FilterQueryBuilder(
            connection: $this->connection,
            alias: $alias,
        );
    }
}