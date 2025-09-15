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

    /**
     * @var array<string, bool> $mapTableAliaseHidden List of table aliases that should not be shown to the user.
     *   Key is the alias, value is true if the table is hidden.
     */
    private array $mapTableAliaseHidden = [];

    /**
     * @var array<string, bool> $mapTableAliasMandatory Map of table aliases that are mandatory.
     *   Key is the alias, value is true if the table is mandatory.
     */
    private array $mapTableAliasMandatory = [];

    public function __construct(
        private readonly Connection $connection,
        private readonly string     $mainTable,
        private readonly string     $mainAlias,
    ) {
        $this->tables[$mainAlias] = $mainTable;
        $this->mapTableAliaseHidden[$mainAlias] = false;
        $this->mapTableAliasMandatory[$mainAlias] = true;
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

    public function getMapTableAliasMandatory(): array
    {
        return $this->mapTableAliasMandatory;
    }

    public function getMandatoryTableAliases(): array
    {
        return \array_keys(\array_filter($this->mapTableAliasMandatory));
    }

    public function setTableAliasMandatory(string $alias, bool $mandatory = true): static
    {
        $this->mapTableAliasMandatory[$alias] = $mandatory;

        return $this;
    }

    public function isTableAliasMandatory(string $alias): bool
    {
        return $this->mapTableAliasMandatory[$alias] ?? false;
    }

    public function getMapTableAliaseHidden(): array
    {
        return $this->mapTableAliaseHidden;
    }

    public function getHiddenTableAliases(): array
    {
        return \array_keys(\array_filter($this->mapTableAliaseHidden));
    }

    public function isTableAliasHidden(string $alias): bool
    {
        return $this->mapTableAliaseHidden[$alias] ?? false;
    }

    public function setTableAliasHidden(string $alias, bool $hidden = true): static
    {
        $this->mapTableAliaseHidden[$alias] = $hidden;

        return $this;
    }

    /**
     * Returns a quoted column name with an optional table alias.
     *
     * @param string      $column  The column name.
     * @param string|null $of  The table alias of the table to which the column belongs.
     * @param bool|null   $quoteColumn  Whether to quote the column name.
     * @return string
     */
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

    /**
     * Adds a column to the select clause.
     *
     * @param string      $column  The column name.
     * @param string|null $of  The table alias of the table to which the column belongs.
     * @param string|null $as  The alias to use for the selected column.
     * @param bool|null   $allowAsterisk  Whether to allow the use of the asterisk (*) character in the column name.
     * @return $this
     */
    public function select(string $column, ?string $of = null, ?string $as = null, ?bool $allowAsterisk = null): static
    {
        $allowAsterisk ??= false;

        if (!$allowAsterisk && \str_contains($column, '*')) {
            throw new \InvalidArgumentException('Cannot use * in ListQueryBuilder::select(...) without explicitly allowing it. Only use * if you know what you are doing.');
        }

        if ($of) {
            $this->mapTableAliasMandatory[$of] = true;
        }

        if ($as) {
            $as = $this->connection->quoteIdentifier($as);
        }

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