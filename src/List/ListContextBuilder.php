<?php

namespace HeimrichHannot\FlareBundle\List;

use Contao\ContentModel;
use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;
use HeimrichHannot\FlareBundle\Projector\ProjectorInterface;
use HeimrichHannot\FlareBundle\Projector\Projectors;
use HeimrichHannot\FlareBundle\SortDescriptor\SortDescriptor;

class ListContextBuilder
{
    private string $context;
    private ?ContentModel $contentModel = null;
    private ?PaginatorConfig $paginatorConfig = null;
    private ?SortDescriptor $sortDescriptor = null;
    private array $properties = [];

    public function __construct(
        private Projectors $projectors,
    ) {}

    public function setContext(string $context): static
    {
        $this->context = $context;
        return $this;
    }

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
        if (!isset($this->context)) {
            throw new \RuntimeException('Context must be set before building the ListContext.');
        }

        return new ListContext(
            context: $this->context,
            paginatorConfig: $this->paginatorConfig,
            sortDescriptor: $this->sortDescriptor,
            contentModel: $this->contentModel,
            properties: $this->properties,
        );
    }
}