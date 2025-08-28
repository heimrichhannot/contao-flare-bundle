<?php

namespace HeimrichHannot\FlareBundle\List;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use HeimrichHannot\FlareBundle\Dto\SqlQuery;
use HeimrichHannot\FlareBundle\Exception\FlareException;

class ListQueryBuilder
{
    private ?ExpressionBuilder $expr = null;
    private array $select = [];
    private array $groupBy = [];

    /**
     * @var array<string, string> $joins Key is the alias, value is the SQL join string.
     */
    private array $joins = [];

    /**
     * @var array<string, string> $tables Key is the alias, value is the table name.
     */
    private array $tables = [];

    public function __construct(
        private readonly Connection $connection,
        private readonly string     $mainTable,
        private readonly string     $mainAlias,
    ) {
        $this->tables[$mainAlias] = $mainTable;
    }

    public function expr(): ExpressionBuilder
    {
        return $this->expr ??= $this->connection->createExpressionBuilder();
    }

    public function getMainTable(bool $quoted = false): string
    {
        return !$quoted ? $this->mainTable : $this->connection->quoteIdentifier($this->mainTable);
    }

    public function getMainAlias(bool $quoted = false): string
    {
        return !$quoted ? $this->mainAlias : $this->connection->quoteIdentifier($this->mainAlias);
    }

    public function getTables(): array
    {
        return $this->tables;
    }

    public function getTable(string $alias): ?string
    {
        return $this->tables[$alias] ?? null;
    }

    public function column(string $column, ?string $of = null, ?bool $quoteColumn = null): string
    {
        if (!$column = \trim($column)) {
            throw new \InvalidArgumentException('Column name must not be empty');
        }

        $quoteColumn ??= true;
        $of ??= $this->mainAlias;

        if (!$quoteColumn && !\preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column)) {
            throw new \InvalidArgumentException("Invalid column name format: {$column}");
        }

        if (!$quoteColumn || $column === '*') {
            return $this->connection->quoteIdentifier($of) . '.' . $column;
        }

        return $this->connection->quoteIdentifier($of . '.' . $column);
    }

    public function select(string $column, ?string $of = null, ?string $as = null): static
    {
        $as = $as ? $this->connection->quoteIdentifier($as) : null;
        return $this->addRawSelect($this->column($column, $of) . ($as ? ' AS ' . $as : ''));
    }

    /**
     * Adds a raw expression to the select clause.
     *
     * ⚠ Use with caution: no escaping or quoting is applied.
     * Prefer {@see select()} in most cases.
     */
    public function addRawSelect(string $select): static
    {
        $this->select[] = $select;

        return $this;
    }

    public function getSelect(): array
    {
        return $this->select;
    }

    public function groupBy(string $column, ?string $of = null): static
    {
        $this->groupBy[] = $this->column($column, $of);

        return $this;
    }

    public function getGroupBy(): array
    {
        return $this->groupBy;
    }

    public function getJoins(): array
    {
        return $this->joins;
    }

    /**
     * Adds any JOIN to the query.
     *
     * @throws FlareException If the join alias is already in use or the join condition is invalid.
     * @internal Do not use this method directly, use the join methods instead: {@see innerJoin} and {@see leftJoin}.
     */
    public function addJoin(string $join, string $table, string $alias, string $condition): static
    {
        if (!\preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias)) {
            throw new FlareException("Invalid join alias format: {$alias}");
        }

        if (!\preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) {
            throw new FlareException("Invalid join table format: {$table}");
        }

        if (!\str_contains($join, 'JOIN')) {
            throw new FlareException("Invalid join type: {$join}");
        }

        if (!\str_contains($condition, '=')) {
            throw new FlareException("Invalid join condition: {$condition}");
        }

        if (isset($this->joins[$alias])) {
            throw new FlareException("Join alias '{$alias}' is already in use.");
        }

        $qTable = $this->connection->quoteIdentifier($table);
        $qAlias = $this->connection->quoteIdentifier($alias);

        $this->tables[$alias] = $table;
        $this->joins[$alias] = \sprintf('%s %s AS %s ON %s', $join, $qTable, $qAlias, $condition);

        return $this;
    }

    /**
     * Adds a LEFT JOIN to the query.
     *
     * @throws FlareException If the join alias is already in use.
     */
    public function leftJoin(string $table, string $as, string $on): static
    {
        return $this->addJoin('LEFT JOIN', $table, $as, $on);
    }

    /**
     * Adds an INNER JOIN to the query.
     *
     * @throws FlareException If the join alias is already in use.
     */
    public function innerJoin(string $table, string $as, string $on): static
    {
        return $this->addJoin('INNER JOIN', $table, $as, $on);
    }

    public function makeJoinOn(string $joinAlias, string $joinColumn, string $mainColumn): string
    {
        return $this->expr()->eq(
            $this->column($mainColumn),
            $this->column($joinColumn, of: $joinAlias),
        );
    }

    public function buildQuery(): SqlQuery
    {
        return new SqlQuery(
            select: $this->getSelect(),
            from: $this->getMainTable(true),
            fromAlias: $this->getMainAlias(true),
            joins: $this->getJoins(),
            groupBy: $this->getGroupBy(),
        );
    }
}