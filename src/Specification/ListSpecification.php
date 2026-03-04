<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Specification;

use HeimrichHannot\FlareBundle\Collection\FilterDefinitionCollection;
use HeimrichHannot\FlareBundle\Model\DocumentsListModelTrait;
use HeimrichHannot\FlareBundle\Specification\DataSource\ListDataSourceInterface;

class ListSpecification
{
    use AutoItemFieldGetterTrait;
    use DocumentsListModelTrait;
    use DynamicPropertiesTrait;

    public function __construct(
        public readonly string              $type,
        public readonly string              $dc,
        private ?ListDataSourceInterface    $dataSource = null,
        private ?FilterDefinitionCollection $filters = null,
    ) {
        $this->filters ??= new FilterDefinitionCollection();
    }

    public function getDataSource(): ?ListDataSourceInterface
    {
        return $this->dataSource;
    }

    public function setDataSource(?ListDataSourceInterface $dataSource): static
    {
        $this->dataSource = $dataSource;
        return $this;
    }

    public function getFilters(): FilterDefinitionCollection
    {
        return $this->filters;
    }

    public function setFilters(FilterDefinitionCollection $filters): void
    {
        $this->filters = $filters;
    }

    public function hash(): string
    {
        return \sha1(\serialize([
            $this->type,
            $this->filters->hash(),
            'model' => $this->dataSource ? [
                $this->dataSource->id,
                $this->dataSource->type,
                $this->dataSource->dc,
            ] : null,
        ]));
    }

    public function __clone(): void
    {
        $this->filters = clone $this->filters;
    }
}