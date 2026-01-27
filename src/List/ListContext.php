<?php

namespace HeimrichHannot\FlareBundle\List;

use Contao\ContentModel;
use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;
use HeimrichHannot\FlareBundle\SortDescriptor\SortDescriptor;

readonly class ListContext
{
    public const AGGREGATION = 'aggregation';
    public const EXPORT = 'export';
    public const INTERACTIVE = 'interactive';
    public const VALIDATION = 'validation';

    public function __construct(
        public ?PaginatorConfig $paginatorConfig,
        public ?SortDescriptor  $sortDescriptor,
        public ?ContentModel    $contentModel,
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
}