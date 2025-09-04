<?php

namespace HeimrichHannot\FlareBundle\Dto;

readonly class SqlQuery implements \Stringable
{
    public function __construct(
        private array   $select,
        private string  $from,
        private ?string $fromAlias = null,
        private string  $conditions = '1 = 1',
        private array   $joins = [],
        private array   $groupBy = [],
        private array   $having = [],
        private array   $orderBy = [],
        private ?int    $limit = null,
        private ?int    $offset = null,
    ) {}

    public function getSelect(): array
    {
        return $this->select;
    }

    public function getFrom(): string
    {
        return $this->from;
    }

    public function getFromAlias(): string
    {
        return $this->fromAlias;
    }

    public function getConditions(): string
    {
        return $this->conditions;
    }

    public function getJoins(): array
    {
        return $this->joins;
    }

    public function getGroupBy(): array
    {
        return $this->groupBy;
    }

    public function getHaving(): array
    {
        return $this->having;
    }

    public function getOrderBy(): array
    {
        return $this->orderBy;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function getTableAliases(): array
    {
        $aliases = \array_keys($this->joins);
        $aliases[] = $this->fromAlias;
        return $aliases;
    }

    public function withFilteredJoins(array $keepAliases): self
    {
        $joins = [];
        foreach ($keepAliases as $alias) {
            if (isset($this->joins[$alias])) {
                $joins[$alias] = $this->joins[$alias];
            }
        }

        return new self(
            select: $this->select,
            from: $this->from,
            fromAlias: $this->fromAlias,
            conditions: $this->conditions,
            joins: $joins,
            groupBy: $this->groupBy,
            having: $this->having,
            orderBy: $this->orderBy,
            limit: $this->limit,
            offset: $this->offset,
        );
    }

    public function sqlify(
        array|string|null       $select = null,
        ?string                 $from = null,
        ?string                 $fromAlias = null,
        ?string                 $conditions = null,
        ?array                  $joins = null,
        array|string|false|null $having = null,
        array|string|false|null $groupBy = null,
        array|string|false|null $orderBy = null,
        ?int                    $limit = null,
        ?int                    $offset = null,
    ): string {
        $select ??= $this->select;
        $select = (array) $select;

        $from ??= $this->from;
        $fromAlias ??= $this->fromAlias;
        $conditions = ($conditions ?? $this->conditions) ?: '1 = 1';
        $joins ??= $this->joins;

        $having ??= $this->having;
        $having = $having === false ? null : (array) $having;

        $groupBy ??= $this->groupBy;
        $groupBy = $groupBy === false ? null : (array) $groupBy;

        $orderBy ??= $this->orderBy;
        $orderBy = $orderBy === false ? null : (array) $orderBy;

        $limit ??= $this->limit;
        $offset ??= $this->offset;

        if (!$select || !$from) {
            throw new \InvalidArgumentException("Invalid query parameters: select or from is missing.");
        }

        $sql = \sprintf('SELECT %s FROM %s', \implode(', ', $select), $from);

        if ($fromAlias) {
            $sql .= \sprintf(' AS %s', $fromAlias);
        }

        if (!empty($joins)) {
            $sql .= \sprintf(' %s', \implode(' ', $joins));
        }

        $sql .= \sprintf(' WHERE %s', $conditions);

        if (!empty($having)) {
            $sql .= \sprintf(' HAVING %s', \implode(' AND ', $having));
        }

        if (!empty($groupBy)) {
            $sql .= \sprintf(' GROUP BY %s', \implode(', ', $groupBy));
        }

        if (!empty($orderBy)) {
            $sql .= \sprintf(' ORDER BY %s', \implode(', ', $orderBy));
        }

        if ($limit) {
            $sql .= \sprintf(' LIMIT %d', $limit);
        }

        if ($offset) {
            $sql .= \sprintf(' OFFSET %d', $offset);
        }

        return \trim($sql);
    }

    public function __toString(): string
    {
        return $this->sqlify();
    }
}