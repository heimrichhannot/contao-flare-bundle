<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\DataContainer\Builder;

interface DcaBuilderInterface
{
    public function palette(?string $palette): self;

    public function getPalette(): ?string;

    public function prefix(string|callable|null $prefix): self;

    public function suffix(string|callable|null $suffix): self;

    public function field(string $name): DcaFieldBuilderInterface;

    /**
     * Returns a sub-builder for a palette segment (`'control'`): its `palette()` fills that segment,
     * everything else is shared with this builder.
     */
    public function scope(string $segment): self;

    /**
     * Registers `$field` as a palette selector and `$palette` as its subpalette
     * (`$field` for checkbox selectors, `{$field}_{$value}` for select selectors).
     */
    public function selector(string $field, string $palette, ?string $value = null): self;

    public function apply(string $table, string $type, bool $applyPalette = true): void;
}
