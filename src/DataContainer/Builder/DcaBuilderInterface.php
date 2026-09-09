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

    public function apply(string $table, string $type, bool $applyPalette = true): void;
}
