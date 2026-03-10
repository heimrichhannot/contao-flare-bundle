<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Query;

readonly class FilterQuery
{
    public function __construct(
        private string $targetAlias,
        private string $sql,
        private array  $params,
        private array  $types,
    ) {}

    public function getTargetAlias(): string
    {
        return $this->targetAlias;
    }

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