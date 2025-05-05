<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Collection\Collection;
use HeimrichHannot\FlareBundle\Model\ListModel;

/**
 * A type-safe collection specifically for FilterContext objects.
 *
 * @extends Collection<FilterContext>
 */
class FilterContextCollection extends Collection
{
    protected ListModel $listModel;
    protected ?string $table;

    public function getListModel(): ListModel
    {
        return $this->listModel;
    }

    public function setListModel(ListModel $listModel): static
    {
        $this->listModel = $listModel;

        return $this;
    }

    public function getTable(): ?string
    {
        return $this->table;
    }

    public function setTable(?string $table): static
    {
        $this->table = $table;

        return $this;
    }

    /** {@inheritDoc} */
    protected function getItemType(): string
    {
        return FilterContext::class;
    }

    public static function create(ListModel $listModel): static
    {
        return (new static())
            ->setListModel($listModel)
            ->setTable($listModel->dc);
    }
}