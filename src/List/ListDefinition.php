<?php

namespace HeimrichHannot\FlareBundle\List;

use Contao\PageModel;
use HeimrichHannot\FlareBundle\Filter\FilterDefinitionCollection;
use HeimrichHannot\FlareBundle\Model\DocumentsListModelTrait;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Trait\AutoItemFieldGetterTrait;
use HeimrichHannot\FlareBundle\Trait\DynamicPropertiesTrait;

class ListDefinition
{
    use AutoItemFieldGetterTrait;
    use DocumentsListModelTrait;
    use DynamicPropertiesTrait;

    public FilterDefinitionCollection $filters;
    private string $filterFormName;

    public function __construct(
        public readonly string      $type,
        public readonly string      $dc,
        private ?ListModel          $sourceListModel = null,
        ?FilterDefinitionCollection $filters = null,
        ?string                     $filterFormName = null,
        private ?int                $filterFormActionPageId = null,
    ) {
        $this->filters = $filters ?? new FilterDefinitionCollection();
        $this->filterFormName = $filterFormName ?? 'flare';
    }

    public function getSourceListModel(): ?ListModel
    {
        return $this->sourceListModel;
    }

    public function setSourceListModel(?ListModel $sourceListModel): static
    {
        $this->sourceListModel = $sourceListModel;
        return $this;
    }

    public function getFilters(): FilterDefinitionCollection
    {
        return $this->filters;
    }

    public function getFilterFormName(): string
    {
        return $this->filterFormName;
    }

    public function setFilterFormName(string $filterFormName): static
    {
        if (!$filterFormName) {
            throw new \InvalidArgumentException('Filter form name must not be empty');
        }

        $this->filterFormName = $filterFormName;
        return $this;
    }

    public function getFilterFormActionPageId(): ?int
    {
        return $this->filterFormActionPageId;
    }

    public function setFilterFormActionPageId(?int $filterFormActionPageId): static
    {
        $this->filterFormActionPageId = $filterFormActionPageId;
        return $this;
    }

    public function getFormAction(): ?string
    {
        if (!$jumpTo = $this->getFilterFormActionPageId()) {
            return null;
        }

        if (!$pageModel = PageModel::findByPk($jumpTo)) {
            return null;
        }

        return $pageModel->getAbsoluteUrl();
    }

    public function hash(): string
    {
        return \sha1(\serialize([
            $this->type,
            $this->filters->hash(),
            $this->filterFormName,
            $this->filterFormActionPageId,
            'model' => $this->sourceListModel ? [
                $this->sourceListModel->id,
                $this->sourceListModel->type,
                $this->sourceListModel->dc,
            ] : null,
        ]));
    }

    public function __clone(): void
    {
        $this->filters = clone $this->filters;
    }
}