<?php

namespace HeimrichHannot\FlareBundle\Query;

use Symfony\Component\Validator\Constraints as Assert;

class SqlQueryStruct
{
    /**
     * @param array<string, SqlJoinStruct> $joins
     */
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Count(min: 1, minMessage: 'At least one column must be selected.')]
        private ?array  $select = null,
        #[Assert\NotBlank]
        private ?string $from = null,
        private ?string $fromAlias = null,
        private ?string $conditions = null,
        private array   $joins = [],
        private ?array  $having = null,
        private ?array  $groupBy = null,
        private ?array  $orderBy = null,
        private ?int    $limit = null,
        private ?int    $offset = null,
        private array   $params = [],
        private array   $types = [],
    ) {
        $this->updateJoinKeys();
    }

    private function updateJoinKeys(): void
    {
        $seen = [];
        foreach ($this->joins as $join) {
            if (isset($seen[$join->joinAlias])) {
                throw new \LogicException("Duplicate join alias: $join->joinAlias");
            }
            $seen[$join->joinAlias] = true;
        }

        $this->joins = \array_combine(
            \array_map(static fn (SqlJoinStruct $join): string => $join->joinAlias, $this->joins),
            $this->joins
        ) ?: [];
    }

    public function getSelect(): ?array
    {
        return $this->select;
    }

    public function setSelect(array|string $select): self
    {
        $this->select = (array) $select;

        return $this;
    }

    public function getFrom(): ?string
    {
        return $this->from;
    }

    public function setFrom(?string $from): self
    {
        $this->from = $from;

        return $this;
    }

    public function getFromAlias(): ?string
    {
        return $this->fromAlias;
    }

    public function setFromAlias(?string $fromAlias): self
    {
        $this->fromAlias = $fromAlias;

        return $this;
    }

    public function getConditions(): ?string
    {
        return $this->conditions;
    }

    public function setConditions(?string $conditions): self
    {
        $this->conditions = $conditions;

        return $this;
    }

    /** @return array<string, SqlJoinStruct> */
    public function getJoins(): array
    {
        return $this->joins;
    }

    public function setJoins(array $joins): self
    {
        $this->joins = $joins;
        $this->updateJoinKeys();

        return $this;
    }

    public function getHaving(): ?array
    {
        return $this->having;
    }

    public function setHaving(array|string|null $having): self
    {
        $this->having = $having ? (array) $having : null;

        return $this;
    }

    public function getGroupBy(): ?array
    {
        return $this->groupBy;
    }

    public function setGroupBy(array|string|null $groupBy): self
    {
        $this->groupBy = $groupBy ? (array) $groupBy : null;

        return $this;
    }

    public function getOrderBy(): ?array
    {
        return $this->orderBy;
    }

    public function setOrderBy(array|string|null $orderBy): self
    {
        $this->orderBy = $orderBy ? (array) $orderBy : null;

        return $this;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function setLimit(?int $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    public function getOffset(): ?int
    {
        return $this->offset;
    }

    public function setOffset(?int $offset): self
    {
        $this->offset = $offset;

        return $this;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function setParams(array $params): self
    {
        $this->params = $params;

        return $this;
    }

    public function getTypes(): array
    {
        return $this->types;
    }

    public function setTypes(array $types): self
    {
        $this->types = $types;

        return $this;
    }

    public function getTableAliases(): array
    {
        $aliases = \array_keys($this->joins);
        $aliases[] = $this->fromAlias;
        return $aliases;
    }

    public function filterJoinAliases(array $keepAliases): self
    {
        $joins = [];
        foreach ($keepAliases as $alias) {
            if (isset($this->joins[$alias])) {
                $joins[$alias] = $this->joins[$alias];
            }
        }

        $this->setJoins($joins);

        return $this;
    }

    public static function create(): self
    {
        return new self();
    }
}