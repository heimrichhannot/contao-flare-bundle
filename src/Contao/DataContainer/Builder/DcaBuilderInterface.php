<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contao\DataContainer\Builder;

interface DcaBuilderInterface
{
    public function palette(?string $palette): self;

    public function getPalette(): ?string;

    public function prefix(string|callable|null $prefix): self;

    public function suffix(string|callable|null $suffix): self;

    public function field(string $name): DcaFieldBuilderInterface;

    /**
     * Registers `$field` as a palette selector and `$palette` as its subpalette
     * (`$field` for checkbox selectors, `{$field}_{$value}` for select selectors).
     */
    public function selector(string $field, string $palette, ?string $value = null): self;
}
