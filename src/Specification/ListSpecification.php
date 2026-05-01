<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Specification;

use HeimrichHannot\FlareBundle\Collection\ConfiguredFilterCollection;
use HeimrichHannot\FlareBundle\Model\DocumentsListModelTrait;
use HeimrichHannot\FlareBundle\Specification\DataSource\ListDataSourceInterface;

#[\AllowDynamicProperties]
class ListSpecification
{
    use AutoItemFieldGetterTrait;
    use DocumentsListModelTrait;
    use DynamicPropertiesTrait;

    public function __construct(
        public readonly string              $type,
        public readonly string              $dc,
        private ?ListDataSourceInterface    $dataSource = null,
        private ?ConfiguredFilterCollection $filters = null,
    ) {
        $this->filters ??= new ConfiguredFilterCollection();
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

    public function getFilters(): ConfiguredFilterCollection
    {
        return $this->filters;
    }

    public function setFilters(ConfiguredFilterCollection $filters): void
    {
        $this->filters = $filters;
    }

    public function hash(): string
    {
        return \sha1(\serialize([
            $this->type,
            $this->dc,
            $this->filters->hash(),
            'model' => $this->dataSource ? [
                $this->dataSource->getListIdentifier(),
                $this->dataSource->getListType(),
                $this->dataSource->getListTable(),
            ] : null,
        ]));
    }

    public function __clone(): void
    {
        $this->filters = clone $this->filters;
    }
}