<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Filter\Type\FilterTypeInterface;

interface FilterBuilderInterface
{
    /**
     * @param class-string<FilterTypeInterface> $type
     * @param array<string, mixed> $options
     */
    public function add(string $type, array $options = [], ?string $targetAlias = null): static;

    /**
     * @return FilterCall[]
     */
    public function all(): array;

    public function abort(): never;
}