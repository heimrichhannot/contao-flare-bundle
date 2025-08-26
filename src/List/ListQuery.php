<?php

namespace HeimrichHannot\FlareBundle\List;

readonly class ListQuery
{
    public function __construct(
        private array  $select,
        private string $from,
        private array  $joins,
        private array  $groupBy,
        private array  $having = [],
    ) {}

    public function getSelect(): array
    {
        return $this->select;
    }

    public function getFrom(): string
    {
        return $this->from;
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

    public function createQueryWith(
        ?string $conditions,
        ?string $orderBy = null,
        ?int $limit = null,
        ?int $offset = null,
    ): string {
        $sql = \sprintf(
            'SELECT %s FROM %s %s WHERE %s GROUP BY %s',
            \implode(', ', $this->select),
            $this->from,
            \implode(' ', $this->joins),
            $conditions ?: '1 = 1',
            \implode(', ', $this->groupBy),
        );

        if ($orderBy) {
            $sql .= \sprintf(' ORDER BY %s', $orderBy);
        }

        if ($limit) {
            $sql .= \sprintf(' LIMIT %d', $limit);
        }

        if ($offset) {
            $sql .= \sprintf(' OFFSET %d', $offset);
        }

        return $sql;
    }
}