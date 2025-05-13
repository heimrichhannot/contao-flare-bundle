<?php

namespace HeimrichHannot\FlareBundle\Dto;

class FilteredQueryDto
{
    public function __construct(
        private readonly string $query,
        private readonly array $params,
        private readonly array $types,
        private readonly bool $allowed,
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