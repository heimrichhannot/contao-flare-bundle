<?php /** @noinspection ClassMethodNameMatchesFieldNameInspection */

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\DataContainer\Builder;

use HeimrichHannot\FlareBundle\Util\Str;

/**
 * Collects per-type DCA configuration (palette and field tweaks) declared by an element's
 * {@see \HeimrichHannot\FlareBundle\Contract\DcaContract::buildDca()} and by
 * {@see \HeimrichHannot\FlareBundle\Event\ElementDcaEvent} listeners, then materializes
 * it into the live `$GLOBALS['TL_DCA']` array.
 */
final class DcaBuilder implements DcaBuilderInterface
{
    private ?string $palette = null;

    private string|\Closure|null $prefix = null;

    private string|\Closure|null $suffix = null;

    /**
     * @var array<string, DcaFieldBuilder>
     */
    private array $fields = [];

    /**
     * Sets the element's palette part. It is merged between the table's
     * `__prefix__` and `__suffix__` palettes. Pass null for no own fields.
     */
    public function palette(?string $palette): self
    {
        $this->palette = $palette;
        return $this;
    }

    public function getPalette(): ?string
    {
        return $this->palette;
    }

    /**
     * Overrides the palette prefix for this type: a string replaces the table's `__prefix__`,
     * a callable `fn(string $current): string` transforms it, null keeps it.
     */
    public function prefix(string|callable|null $prefix): self
    {
        $this->prefix = \is_callable($prefix) ? $prefix(...) : $prefix;
        return $this;
    }

    /**
     * Overrides the palette suffix for this type: a string replaces the table's `__suffix__`,
     * a callable `fn(string $current): string` transforms it, null keeps it.
     */
    public function suffix(string|callable|null $suffix): self
    {
        $this->suffix = \is_callable($suffix) ? $suffix(...) : $suffix;
        return $this;
    }

    /**
     * Returns the (shared) field builder for per-type tweaks of a DCA field definition.
     */
    public function field(string $name): DcaFieldBuilder
    {
        return $this->fields[$name] ??= new DcaFieldBuilder();
    }

    /**
     * Writes the collected configuration into `$GLOBALS['TL_DCA'][$table]`.
     *
     * @internal Called by the FLARE loadDataContainer listener only.
     */
    public function apply(string $table, string $type, bool $applyPalette = true): void
    {
        if (!isset($GLOBALS['TL_DCA'][$table])) {
            return;
        }

        $dca = &$GLOBALS['TL_DCA'][$table];

        if ($applyPalette)
        {
            $prefix = self::resolveAffix($this->prefix, (string) ($dca['palettes']['__prefix__'] ?? ''));
            $suffix = self::resolveAffix($this->suffix, (string) ($dca['palettes']['__suffix__'] ?? ''));

            $dca['palettes'][$type] = Str::mergePalettes($prefix, $this->palette, $suffix);
        }

        foreach ($this->fields as $name => $field)
        {
            if (!\is_array($dca['fields'][$name] ?? null)) {
                $dca['fields'][$name] = [];
            }

            $field->applyTo($dca['fields'][$name]);
        }
    }

    private static function resolveAffix(string|\Closure|null $override, string $current): string
    {
        return match (true) {
            $override instanceof \Closure => (string) $override($current),
            \is_string($override) => $override,
            default => $current,
        };
    }
}
