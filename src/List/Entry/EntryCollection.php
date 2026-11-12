<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List\Entry;

use HeimrichHannot\FlareBundle\Exception\EntryNameCollisionError;
use HeimrichHannot\FlareBundle\Exception\MissingEntryIdError;
use HeimrichHannot\FlareBundle\Exception\SealedEntriesError;

/**
 * The entries of one list: a list (keys 0..n-1), countable, with a fresh iterator per loop.
 *
 * @implements \IteratorAggregate<int, Entry>
 */
final class EntryCollection implements \IteratorAggregate, \Countable
{
    /** @var list<Entry> */
    private array $entries = [];

    /** @var array<string, true> the union of all row keys */
    private array $columns = [];

    /** @var array<string, \Closure(EntryCollection): (\Closure(Entry): mixed)> */
    private array $loaders = [];

    /** @var array<string, \Closure(Entry): mixed> */
    private array $pickers = [];

    private bool $sealed = false;

    /**
     * @param list<array<string, mixed>> $rows
     * @param ?\Closure(Entry): ?string $linker
     * @param ?\Closure(array<string, mixed>): EntryMeta $meta default: an empty EntryMeta
     */
    public function __construct(
        array $rows = [],
        private readonly ?\Closure $linker = null,
        ?\Closure $meta = null,
    ) {
        foreach ($rows as $row) {
            foreach ($row as $key => $_) {
                $this->columns[$key] = true;
            }

            $this->entries[] = new Entry($row, $meta ? $meta($row) : new EntryMeta(), $this);
        }
    }

    /**
     * One {@see ModelRef} per row.
     *
     * @param list<array<string, mixed>> $rows
     * @param ?\Closure(Entry): ?string $linker
     * @throws MissingEntryIdError for a row without a non-empty `id`
     */
    public static function forTable(string $table, array $rows, ?\Closure $linker = null): self
    {
        foreach ($rows as $index => $row) {
            if (($row['id'] ?? null) === null || $row['id'] === '') {
                throw new MissingEntryIdError($table, $index);
            }
        }

        return new self(
            $rows,
            $linker,
            static fn (array $row): EntryMeta => new EntryMeta(new ModelRef($table, $row['id'])),
        );
    }

    /** @return \Iterator<int, Entry> */
    public function getIterator(): \Iterator
    {
        return new \ArrayIterator($this->entries);
    }

    public function count(): int
    {
        return \count($this->entries);
    }

    public function first(): ?Entry
    {
        return $this->entries[0] ?? null;
    }

    /**
     * @return list<mixed> distinct values of a row column, skipping rows without it and empty() values
     */
    public function column(string $name): array
    {
        $values = [];

        foreach ($this->entries as $entry) {
            if (!\array_key_exists($name, $entry->row)) {
                continue;
            }

            $value = $entry->row[$name];

            if ((bool) $value && !\in_array($value, $values, true)) {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * Phase 1 ($load) runs once per collection on the first read of $name; the closure it returns picks the
     * value of one entry (phase 2, memoized by the entry).
     *
     * @param \Closure(EntryCollection): (\Closure(Entry): mixed) $load
     */
    public function attach(string $name, \Closure $load): void
    {
        if ($this->sealed) {
            throw new SealedEntriesError($name);
        }

        if (\in_array($name, Entry::RESERVED, true)) {
            throw new EntryNameCollisionError($name, 'the name is reserved');
        }

        if (isset($this->columns[$name])) {
            throw new EntryNameCollisionError($name, 'a row has a column of that name');
        }

        foreach ($this->entries as $entry) {
            if ($entry->ownsAttachment($name)) {
                throw new EntryNameCollisionError($name, 'an entry already has an attachment of that name');
            }
        }

        if ($this->provides($name)) {
            throw new EntryNameCollisionError($name, 'the collection already has an attachment of that name');
        }

        $this->loaders[$name] = $load;
    }

    public function seal(): void
    {
        $this->sealed = true;
    }

    public function isSealed(): bool
    {
        return $this->sealed;
    }

    /** @internal */
    public function provides(string $name): bool
    {
        return isset($this->loaders[$name]);
    }

    /** @internal runs phase 1 once per name, returns the picker's value for $entry */
    public function resolveFor(string $name, Entry $entry): mixed
    {
        $this->pickers[$name] ??= ($this->loaders[$name])($this);

        return ($this->pickers[$name])($entry);
    }

    /** @internal */
    public function linkFor(Entry $entry): ?string
    {
        return $this->linker ? ($this->linker)($entry) : null;
    }
}
