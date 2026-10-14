<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Query\Factory;

use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Query\FilterConditionsBuilder;

readonly class FilterQueryBuilderFactory
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function create(string $alias): FilterConditionsBuilder
    {
        return new FilterConditionsBuilder(
            connection: $this->connection,
            alias: $alias,
        );
    }
}
