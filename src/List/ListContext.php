<?php

namespace HeimrichHannot\FlareBundle\List;

use Contao\ContentModel;
use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;
use HeimrichHannot\FlareBundle\SortDescriptor\SortDescriptor;

class ListContext
{
    public const AGGREGATION = 'aggregation';
    public const EXPORT = 'export';
    public const INTERACTIVE = 'interactive';
    public const VALIDATION = 'validation';

    public function __construct(
        public ?PaginatorConfig $paginatorConfig = null,
        public ?SortDescriptor  $sortDescriptor = null,
        public ?ContentModel    $contentModel = null,
        private array           $properties = [],
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->properties[$key] ?? $default;
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    public function with(
        ?PaginatorConfig $paginatorConfig = null,
        ?SortDescriptor  $sortDescriptor = null,
        ?ContentModel    $contentModel = null,
        array $properties = [],
    ): self {
        return new self(
            paginatorConfig: $paginatorConfig ?? $this->paginatorConfig,
            sortDescriptor: $sortDescriptor ?? $this->sortDescriptor,
            contentModel: $contentModel ?? $this->contentModel,
            properties: \array_merge($this->properties, $properties),
        );
    }
}