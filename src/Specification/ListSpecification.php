<?php

namespace HeimrichHannot\FlareBundle\Specification;

use HeimrichHannot\FlareBundle\Filter\FilterDefinitionCollection;
use HeimrichHannot\FlareBundle\Model\DocumentsListModelTrait;
use HeimrichHannot\FlareBundle\Specification\DataSource\ListDataSourceInterface;
use HeimrichHannot\FlareBundle\Trait\AutoItemFieldGetterTrait;
use HeimrichHannot\FlareBundle\Trait\DynamicPropertiesTrait;

class ListSpecification
{
    use AutoItemFieldGetterTrait;
    use DocumentsListModelTrait;
    use DynamicPropertiesTrait;

    public FilterDefinitionCollection $filters;

    public function __construct(
        public readonly string           $type,
        public readonly string           $dc,
        private ?ListDataSourceInterface $dataSource = null,
        ?FilterDefinitionCollection      $filters = null,
    ) {
        $this->filters = $filters ?? new FilterDefinitionCollection();
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