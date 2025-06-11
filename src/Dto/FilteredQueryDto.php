<?php

namespace HeimrichHannot\FlareBundle\Dto;

readonly class FilteredQueryDto
{
    public function __construct(
        private string $query,
        private array  $params,
        private array  $types,
        private bool   $allowed,
    ) {}

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function getTypes(): array
    {
        return $this->types;
    }

    public function isAllowed(): bool
    {
        return $this->allowed;
    }

    public static function block(): self
    {
        return new self('SELECT NULL WHERE 1 = 0 LIMIT 0', [], [], false);
    }
}