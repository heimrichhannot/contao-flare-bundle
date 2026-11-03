<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\DataContainer\Builder;

/**
 * The control's view of the {@see DcaBuilder}: its `palette()` fills the control segment of the
 * merged palette; fields, selectors, affixes and scopes are those of the parent builder.
 *
 * @internal Created by {@see DcaBuilder::scope()}.
 */
final class ControlScopeDcaBuilder implements DcaBuilderInterface
{
    private ?string $palette = null;

    /**
     * @param \Closure(?string): mixed $sink Receives the control palette whenever it is set.
     */
    public function __construct(
        private readonly DcaBuilderInterface $parent,
        private readonly \Closure            $sink,
    ) {}

    public function palette(?string $palette): self
    {
        $this->palette = $palette;
        ($this->sink)($palette);

        return $this;
    }

    public function getPalette(): ?string
    {
        return $this->palette;
    }

    public function prefix(string|callable|null $prefix): self
    {
        $this->parent->prefix($prefix);

        return $this;
    }

    public function suffix(string|callable|null $suffix): self
    {
        $this->parent->suffix($suffix);

        return $this;
    }

    public function field(string $name): DcaFieldBuilderInterface
    {
        return $this->parent->field($name);
    }

    public function scope(string $segment): DcaBuilderInterface
    {
        return $this->parent->scope($segment);
    }

    public function selector(string $field, string $palette, ?string $value = null): self
    {
        $this->parent->selector($field, $palette, $value);

        return $this;
    }

    public function apply(string $table, string $type, bool $applyPalette = true): void
    {
        $this->parent->apply($table, $type, $applyPalette);
    }
}
