<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\DataContainer\Builder;

use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\FilterScope;
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
    private FilterScope|false|null $filterScope = null;

    /**
     * @param \Closure(): ?ListExecutionContext $executionContextFactory
     * @param \Closure(): ?FilterScope|null $filterScopeFactory
     */
    public function __construct(
        public readonly string       $table,
        public readonly string       $type,
        public readonly ListModel    $listModel,
        public readonly ?FilterModel $filterModel,
        private readonly \Closure    $executionContextFactory,
        public readonly ?FilterElementInterface $element = null,
        private readonly ?\Closure   $filterScopeFactory = null,
    ) {}

    public function getExecutionContext(): ?ListExecutionContext
    {
        $this->executionContext ??= ($this->executionContextFactory)() ?? false;

        return $this->executionContext ?: null;
    }

    /**
     * The backend scope (SPEC_FILTER_FORMS.md §6.4): the edited filter in a configuration context,
     * or null when it cannot be built.
     */
    public function getFilterScope(): ?FilterScope
    {
        $this->filterScope ??= $this->filterScopeFactory?->__invoke() ?? false;

        return $this->filterScope ?: null;
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
