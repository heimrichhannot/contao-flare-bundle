<?php

namespace HeimrichHannot\FlareBundle\Dto;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Result as DBALResult;

readonly class ParameterizedSqlQuery
{
    public function __construct(
        private string $query,
        private array  $params,
        private array  $types,
        private bool   $allowed,
    ) {}

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function getTypes(): array
    {
        return $this->types;
    }

    public function isAllowed(): bool
    {
        return $this->allowed;
    }

    public static function noResult(): self
    {
        return new self('SELECT NULL WHERE 1 = 0 LIMIT 0', [], [], false);
    }

    /**
     * @throws DBALException When the query fails.
     */
    public function execute(Connection $connection): DBALResult
    {
        return $connection->executeQuery($this->getQuery(), $this->getParams(), $this->getTypes());
    }
}