<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Specification;

use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Model\DocumentsListModelTrait;
use HeimrichHannot\FlareBundle\Specification\DataSource\ListDataSourceInterface;

#[\AllowDynamicProperties]
class ListSpecification
{
    use AutoItemFieldGetterTrait;
    use DocumentsListModelTrait;
    use DynamicPropertiesTrait;

    /**
     * @var array<string, Filter>
     */
    private array $filters = [];

    private int $generatedFilterKeys = 0;

    public function __construct(
        public readonly string           $type,
        public readonly string           $dc,
        private ?ListDataSourceInterface $dataSource = null,
    ) {}

    public function getDataSource(): ?ListDataSourceInterface
    {
        return $this->dataSource;
    }

    public function setDataSource(?ListDataSourceInterface $dataSource): static
    {
        $this->dataSource = $dataSource;
        return $this;
    }

    /**
     * @return array<string, Filter>
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    public function getFilter(string $key): ?Filter
    {
        return $this->filters[$key] ?? null;
    }

    /**
     * Adds a filter. The key defaults to the filter's alias; alias-less filters receive a generated key.
     */
    public function addFilter(Filter $filter, ?string $key = null): static
    {
        $key ??= $filter->alias ?? ('_generated_' . $this->generatedFilterKeys++);
        $this->filters[$key] = $filter;
        return $this;
    }

    public function removeFilter(string $key): static
    {
        unset($this->filters[$key]);
        return $this;
    }

    public function hasFilterOfType(string $elementType): bool
    {
        foreach ($this->filters as $filter)
        {
            if ($filter->getElementType() === $elementType) {
                return true;
            }
        }

        return false;
    }

    public function hash(): string
    {
        return \sha1(\serialize([
            $this->type,
            $this->dc,
            \array_map(static fn (Filter $filter): array => $filter->fingerprint(), $this->filters),
            'model' => $this->dataSource ? [
                $this->dataSource->getListIdentifier(),
                $this->dataSource->getListType(),
                $this->dataSource->getListTable(),
            ] : null,
        ]));
    }
}
