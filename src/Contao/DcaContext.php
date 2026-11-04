<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contao;

use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\ElementContext;
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
    private ElementContext|false|null $elementContext = null;

    /**
     * @param \Closure(): ?ListExecutionContext $executionContextFactory
     * @param \Closure(): ?ElementContext|null $elementContextFactory
     */
    public function __construct(
        public readonly string                  $table,
        public readonly string                  $type,
        public readonly ListModel               $listModel,
        public readonly ?FilterModel            $filterModel,
        private readonly \Closure               $executionContextFactory,
        public readonly ?FilterElementInterface $element = null,
        private readonly ?\Closure              $elementContextFactory = null,
    ) {}

    public function getExecutionContext(): ?ListExecutionContext
    {
        $this->executionContext ??= ($this->executionContextFactory)() ?? false;

        return $this->executionContext ?: null;
    }

    /**
     * The backend context: the edited filter with a configuration intent, or null when it cannot be built.
     */
    public function getElementContext(): ?ElementContext
    {
        $this->elementContext ??= $this->elementContextFactory?->__invoke() ?? false;

        return $this->elementContext ?: null;
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
