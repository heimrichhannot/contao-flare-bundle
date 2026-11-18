<?php /** @noinspection ClassMethodNameMatchesFieldNameInspection */

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contao\DataContainer\Builder;

use Contao\DataContainer;
use HeimrichHannot\FlareBundle\Util\DateTimeHelper;

/**
 * Fluent per-type tweaks for a single DCA field definition. Only applied while a record
 * of the configuring type is being edited, so shared fields stay untouched for other types.
 */
final class DcaFieldBuilder implements DcaFieldBuilderInterface
{
    private array $definition = [];

    /**
     * @var list<list<string>> dot paths split into their keys
     */
    private array $removals = [];

    private bool $datim = false;

    /**
     * @var array{0: string, 1: string}|null
     */
    private ?array $label = null;

    /**
     * @var callable|array|null
     */
    private mixed $options = null;

    /**
     * @var list<callable(mixed, ?DataContainer): mixed>
     */
    private array $load = [];

    /**
     * @var list<callable(mixed, ?DataContainer): mixed>
     */
    private array $save = [];

    public function inputType(string $inputType): self
    {
        $this->definition['inputType'] = $inputType;
        return $this;
    }

    /**
     * Merges values into the field's `eval` configuration.
     */
    public function eval(array $eval): self
    {
        $this->definition['eval'] = \array_merge($this->definition['eval'] ?? [], $eval);
        return $this;
    }

    /**
     * Deep-merges arbitrary keys (reference, default, sql, ...) into the field definition.
     */
    public function merge(array $definition): self
    {
        $this->definition = self::deepMerge($this->definition, $definition);
        return $this;
    }

    /**
     * Removes keys from the field definition, given as dot paths (`'eval.rgxp'`, `'wizard'`). The base definition
     * loses them before the builder's own definition is merged in, and a pending `eval()`/`merge()` value for the
     * path is dropped too, so a later `eval()` or `merge()` sets the key again.
     *
     * Only keys of the definition and of `eval()`/`merge()` are in scope. The label, the options and the callbacks
     * have their own setters, which are applied after the removal; `remove()` does not touch what they set.
     */
    public function remove(string ...$paths): self
    {
        foreach ($paths as $path)
        {
            $keys = \explode('.', $path);
            $this->removals[] = $keys;
            self::unsetPath($this->definition, $keys);
        }

        return $this;
    }

    /**
     * Turns the field into a date-time input (`rgxp` datim, date picker). A stored expression such as `today` is
     * shown as its timestamp, which is what the picker widget reads.
     */
    public function makeDatim(): self
    {
        if ($this->datim) {
            return $this;
        }

        $this->datim = true;
        $this->eval(['rgxp' => 'datim', 'datepicker' => true]);

        return $this->load(static function (mixed $value): mixed {
            if (!\is_string($value)) {
                return $value;
            }

            if (\trim($value) === '') {
                return '';
            }

            if (\is_numeric($value)) {
                return $value;
            }

            return (string) (DateTimeHelper::toDateTimeImmutable($value)?->getTimestamp() ?? '');
        });
    }

    /**
     * Replaces the label rather than merging it: Contao sets a default label as a reference into
     * `$GLOBALS['TL_LANG']`, which a merge would extend or write through.
     */
    public function label(string $label, ?string $description = null): self
    {
        $this->label = [$label, $description ?? ''];
        return $this;
    }

    /**
     * Static options array or an options provider `fn(?DataContainer): array`.
     *
     * @param callable(?DataContainer): array|array $options
     */
    public function options(callable|array $options): self
    {
        $this->options = $options;
        return $this;
    }

    /**
     * Adds a load transform `fn(mixed $value, ?DataContainer $dc): mixed`.
     */
    public function load(callable $fn): self
    {
        $this->load[] = $fn;
        return $this;
    }

    /**
     * Adds a save transform `fn(mixed $value, ?DataContainer $dc): mixed`.
     */
    public function save(callable $fn): self
    {
        $this->save[] = $fn;
        return $this;
    }

    /**
     * @internal Called by {@see DcaBuilder::apply()} only.
     */
    public function applyTo(array &$definition): void
    {
        foreach ($this->removals as $keys) {
            self::unsetPath($definition, $keys);
        }

        $definition = self::deepMerge($definition, $this->definition);

        if ($this->datim)
        {
            $classes = (string) ($definition['eval']['tl_class'] ?? '');

            if (!\in_array('wizard', \preg_split('/\s+/', $classes, -1, \PREG_SPLIT_NO_EMPTY), true)) {
                $definition['eval']['tl_class'] = \trim($classes . ' wizard');
            }
        }

        if ($this->label !== null)
        {
            unset($definition['label']);
            $definition['label'] = $this->label;
        }

        if (\is_array($this->options))
        {
            $definition['options'] = $this->options;
            unset($definition['options_callback']);
        }
        /** @mago-expect lint:no-else-clause This else clause is fine. */
        elseif (\is_callable($this->options))
        {
            $options = $this->options;
            $definition['options_callback'] = static fn (?DataContainer $dc = null): array => $options($dc);
            unset($definition['options']);
        }

        foreach ($this->load as $load)
        {
            $definition['load_callback'] ??= [];
            $definition['load_callback'][] = static fn (mixed $value, ?DataContainer $dc = null): mixed => $load($value, $dc);
        }

        foreach ($this->save as $save)
        {
            $definition['save_callback'] ??= [];
            $definition['save_callback'][] = static fn (mixed $value, ?DataContainer $dc = null): mixed => $save($value, $dc);
        }
    }

    /**
     * @param list<string> $keys
     */
    private static function unsetPath(array &$array, array $keys): void
    {
        $key = \array_shift($keys);

        if ($keys === []) {
            unset($array[$key]);
            return;
        }

        if (\is_array($array[$key] ?? null)) {
            self::unsetPath($array[$key], $keys);
        }
    }

    private static function deepMerge(array $base, array $overlay): array
    {
        foreach ($overlay as $key => $value)
        {
            if (\is_int($key)) {
                $base[] = $value;
                continue;
            }

            if (\is_array($value) && \is_array($base[$key] ?? null)) {
                $base[$key] = self::deepMerge($base[$key], $value);
                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }
}
