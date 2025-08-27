<?php

namespace HeimrichHannot\FlareBundle\Dto;

readonly class SqlQuery
{
    public function __construct(
        private array  $select,
        private string $from,
        private string $conditions = '1 = 1',
        private array  $joins = [],
        private array  $groupBy = [],
        private array  $having = [],
        private array  $orderBy = [],
        private ?int   $limit = null,
        private ?int   $offset = null,
    ) {}

    public function getSelect(): array
    {
        return $this->select;
    }

    public function getFrom(): string
    {
        return $this->from;
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

    public function sqlify(
        array|string|null $select = null,
        ?string $from = null,
        ?string $conditions = null,
        ?array $joins = null,
        array|string|false|null $having = null,
        array|string|false|null $groupBy = null,
        array|string|false|null $orderBy = null,
        ?int $limit = null,
        ?int $offset = null,
    ): string {
        $select ??= $this->select;
        $select = (array) $select;

        $from ??= $this->from;
        $conditions ??= $this->conditions;
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

        $sql = \sprintf(
            'SELECT %s FROM %s %s WHERE %s',
            \implode(', ', $select),
            $from,
            \implode(' ', $joins),
            $conditions,
        );

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

        $sql = \trim($sql);

        return $sql;
    }
}