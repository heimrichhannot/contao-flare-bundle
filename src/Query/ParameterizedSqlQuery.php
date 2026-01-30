<?php

namespace HeimrichHannot\FlareBundle\Query;

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

    /**
     * Creates a new instance with the specified parameters, falling back to the current instance's values.
     *
     * @param string|null $query The query to set, or null to use the current query.
     * @param array|null $params The parameters to set, or null to use the current parameters.
     * @param array|null $types The types to set, or null to use the current types.
     * @param bool|null $allowed Whether the operation is allowed, or null to use the current value.
     *
     * @return static A new instance with the updated values.
     */
    public function with(
        ?string $query = null,
        ?array  $params = null,
        ?array  $types = null,
        ?bool   $allowed = null,
    ): static {
        return new static(
            $query ?? $this->query,
            $params ?? $this->params,
            $types ?? $this->types,
            $allowed ?? $this->allowed,
        );
    }

    /**
     * Executes the query on the given connection.
     *
     * @throws DBALException When the query fails.
     */
    public function execute(Connection $connection): DBALResult
    {
        return $connection->executeQuery($this->getQuery(), $this->getParams(), $this->getTypes());
    }

    /**
     * Creates a new instance which will return an empty result set.
     *
     * @return static
     */
    public static function noResult(): static
    {
        return new static('SELECT NULL WHERE 1 = 0 LIMIT 0', [], [], false);
    }
}