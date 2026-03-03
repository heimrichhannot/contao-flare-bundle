<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Query;

class TableAliasRegistry
{
    use QueryHelperTrait;

    public const ALIAS_MAIN = 'main';

    /**
     * @var array<string, SqlJoinStruct>
     */
    private array $joins = [];

    /**
     * @var array<string, string>
     */
    private array $tables = [];

    /**
     * @var array<string, bool>
     */
    private array $activeAliases = [];

    /**
     * @var array<string, bool>
     */
    private array $hiddenAliases = [];

    public function registerJoin(SqlJoinStruct $join, bool $activate = false, bool $hidden = false): self
    {
        $this->joins[$join->joinAlias] = $join;
        $this->tables[$join->joinAlias] = $join->table;

        if ($activate) {
            $this->activateAlias($join->joinAlias);
        }

        if ($hidden) {
            $this->hideAlias($join->joinAlias);
        }

        return $this;
    }

    public function activateAlias(string $alias): self
    {
        $this->activeAliases[$alias] = true;
        return $this;
    }

    public function hideAlias(string $alias): self
    {
        if ($alias !== self::ALIAS_MAIN) {
            $this->hiddenAliases[$alias] = true;
        }
        return $this;
    }

    public function showAlias(string $alias): self
    {
        unset($this->hiddenAliases[$alias]);
        return $this;
    }

    public function setMainTable(string $table): self
    {
        $this->setTable(self::ALIAS_MAIN, $table, activate: true);
        return $this;
    }

    public function setTable(string $alias, string $table, bool $activate = false, bool $hidden = false): self
    {
        $this->tables[$alias] = $table;

        if ($activate) {
            $this->activateAlias($alias);
        }

        if ($hidden) {
            $this->hideAlias($alias);
        }

        return $this;
    }

    public function getTables(): array
    {
        return $this->tables;
    }

    public function getTable(string $alias): ?string
    {
        return $this->tables[$alias] ?? null;
    }

    public function isTableAliasHidden(string $alias): bool
    {
        return $this->hiddenAliases[$alias] ?? false;
    }

    public function setTableAliasHidden(string $alias, bool $hidden = true): self
    {
        $this->hiddenAliases[$alias] = $hidden;
        return $this;
    }

    public function getAliases(): array
    {
        return \array_keys($this->tables);
    }

    public function getActiveAliases(): array
    {
        return \array_keys(\array_filter($this->activeAliases));
    }

    public function getHiddenAliases(): array
    {
        return \array_keys(\array_filter($this->hiddenAliases));
    }

    public function getVisibleAliases(): array
    {
        return \array_diff($this->getAliases(), $this->getHiddenAliases());
    }

    /**
     * @return SqlJoinStruct[]
     */
    public function resolveActiveJoins(): array
    {
        $visitedAliases = [];
        $stack = $this->getActiveAliases();

        while (!empty($stack))
        {
            $alias = \array_pop($stack);

            if ($alias === self::ALIAS_MAIN || isset($visitedAliases[$alias])) {
                continue;
            }

            if (!isset($this->joins[$alias])) {
                continue;
            }

            $visitedAliases[$alias] = true;
            $join = $this->joins[$alias];

            // Add the 'fromAlias' and any other 'requires' to the stack to be resolved.
            if ($join->fromAlias !== self::ALIAS_MAIN) {
                $stack[] = $join->fromAlias;
            }
            
            foreach ($join->requires as $requiredAlias) {
                if ($requiredAlias !== self::ALIAS_MAIN) {
                    $stack[] = $requiredAlias;
                }
            }
        }

        return $this->sortJoins(\array_keys($visitedAliases));
    }

    /**
     * @param string[] $aliases
     * @return SqlJoinStruct[]
     */
    private function sortJoins(array $aliases): array
    {
        $sorted = [];
        $visited = [];

        $visit = function (string $alias) use (&$visit, &$sorted, &$visited): void {
            if (isset($visited[$alias]) || $alias === self::ALIAS_MAIN || !isset($this->joins[$alias])) {
                return;
            }

            $visited[$alias] = true;
            $join = $this->joins[$alias];

            if ($join->fromAlias !== self::ALIAS_MAIN) {
                $visit($join->fromAlias);
            }
            
            foreach ($join->requires as $req) {
                if ($req !== self::ALIAS_MAIN) {
                    $visit($req);
                }
            }

            $sorted[$alias] = $join;
        };

        foreach ($aliases as $alias) {
            $visit($alias);
        }

        return $sorted;
    }

    public function getJoin(string $alias): ?SqlJoinStruct
    {
        return $this->joins[$alias] ?? null;
    }
}