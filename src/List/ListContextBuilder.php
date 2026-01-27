<?php

namespace HeimrichHannot\FlareBundle\List;

use Contao\ContentModel;
use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;
use HeimrichHannot\FlareBundle\SortDescriptor\SortDescriptor;

class ListContextBuilder
{
    private ?ContentModel $contentModel = null;
    private ?PaginatorConfig $paginatorConfig = null;
    private ?SortDescriptor $sortDescriptor = null;
    private array $properties = [];

    public function setPaginatorConfig(?PaginatorConfig $paginatorConfig): static
    {
        $this->paginatorConfig = $paginatorConfig;
        return $this;
    }

    public function setSortDescriptor(?SortDescriptor $sortDescriptor): static
    {
        $this->sortDescriptor = $sortDescriptor;
        return $this;
    }

    public function setContentModel(?ContentModel $contentModel): static
    {
        $this->contentModel = $contentModel;
        return $this;
    }

    public function set(string $key, mixed $value): static
    {
        $this->properties[$key] = $value;
        return $this;
    }

    public function build(): ListContext
    {
        return new ListContext(
            paginatorConfig: $this->paginatorConfig,
            sortDescriptor: $this->sortDescriptor,
            contentModel: $this->contentModel,
            properties: $this->properties,
        );
    }
}