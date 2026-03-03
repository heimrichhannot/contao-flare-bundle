<?php

namespace HeimrichHannot\FlareBundle\Filter;

readonly class FilterQuery
{
    public function __construct(
        private string $sql,
        private array  $params,
        private array  $types,
    ) {}

    public function getParams(): array
    {
        return $this->params;
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    public function getTypes(): array
    {
        return $this->types;
    }
}