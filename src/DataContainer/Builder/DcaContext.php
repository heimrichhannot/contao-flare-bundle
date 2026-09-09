<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\DataContainer\Builder;

use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Query\ListExecutionContext;

/**
 * Backend configuration context: the record being edited plus lazy access to the
 * list's execution context (table aliases, target tables).
 */
final class DcaContext
{
    private ListExecutionContext|false|null $executionContext = null;

    /**
     * @param \Closure(): ?ListExecutionContext $executionContextFactory
     */
    public function __construct(
        public readonly string       $table,
        public readonly string       $type,
        public readonly ListModel    $listModel,
        public readonly ?FilterModel $filterModel,
        private readonly \Closure    $executionContextFactory,
    ) {}

    public function getExecutionContext(): ?ListExecutionContext
    {
        if ($this->executionContext === null) {
            $this->executionContext = ($this->executionContextFactory)() ?? false;
        }

        return $this->executionContext ?: null;
    }

    /**
     * @return array<string, string> Table names by alias.
     */
    public function getTables(): array
    {
        return $this->getExecutionContext()?->tableAliasRegistry->getTables() ?? [];
    }

    /**
     * The table the filter's conditions target: the configured target alias' table,
     * falling back to the list's data container.
     */
    public function getTargetTable(): string
    {
        $targetAlias = (string) ($this->filterModel->targetAlias ?? '');

        return $this->getExecutionContext()?->tableAliasRegistry->getTable($targetAlias) ?: $this->listModel->dc;
    }
}
