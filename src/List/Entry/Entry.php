<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List\Entry;

use HeimrichHannot\FlareBundle\Exception\EntryNameCollisionError;
use HeimrichHannot\FlareBundle\Exception\ReadonlyEntryError;
use HeimrichHannot\FlareBundle\Exception\SealedEntriesError;
use HeimrichHannot\FlareBundle\Exception\UndefinedEntryKeyError;

/**
 * One row of a list, independent of the data source. Names resolve in this order: `to`, a row column, an
 * attachment of this entry, an attachment of its collection. `row` and `meta` are declared properties.
 */
final class Entry
{
    /** Names that are never resolved from the row; a column of that name stays reachable as `entry.row.<name>`. */
    public const RESERVED = ['to', 'row', 'meta'];

    /** @var array<string, mixed> */
    private array $values = [];

    /** @var array<string, \Closure(Entry): mixed> */
    private array $lazy = [];

    /** @var array<string, mixed> */
    private array $provided = [];

    private bool $linked = false;
    private ?string $link = null;

    /**
     * @param array<string, mixed> $row
     * @internal entries are created by their EntryCollection
     */
    public function __construct(
        public readonly array             $row,
        public readonly EntryMeta         $meta = new EntryMeta(),
        private readonly ?EntryCollection $collection = null,
    ) {}

    /** The reader URL from the collection's linker, asked once per entry. */
    public function to(): ?string
    {
        if (!$this->linked) {
            $this->link = $this->collection?->linkFor($this);
            $this->linked = true;
        }

        return $this->link;
    }

    /** A value, or a `\Closure(Entry): mixed` that runs once on the first access. */
    public function attach(string $name, mixed $value): void
    {
        if ($this->collection?->isSealed()) {
            throw new SealedEntriesError($name);
        }

        if (\in_array($name, self::RESERVED, true)) {
            throw new EntryNameCollisionError($name, 'the name is reserved');
        }

        if (\array_key_exists($name, $this->row)) {
            throw new EntryNameCollisionError($name, 'the row has a column of that name');
        }

        if ($this->ownsAttachment($name)) {
            throw new EntryNameCollisionError($name, 'the entry already has an attachment of that name');
        }

        if ($this->collection?->provides($name)) {
            throw new EntryNameCollisionError($name, 'the collection provides an attachment of that name');
        }

        if ($value instanceof \Closure) {
            $this->lazy[$name] = $value;
            return;
        }

        $this->values[$name] = $value;
    }

    public function __get(string $name): mixed
    {
        if ($name === 'to') {
            return $this->to();
        }

        if (\array_key_exists($name, $this->row)) {
            return $this->row[$name];
        }

        if ($this->ownsAttachment($name)) {
            if (isset($this->lazy[$name])) {
                $load = $this->lazy[$name];
                unset($this->lazy[$name]);
                $this->values[$name] = $load($this);
            }

            return $this->values[$name];
        }

        if ($this->collection?->provides($name)) {
            if (!\array_key_exists($name, $this->provided)) {
                $this->provided[$name] = $this->collection->resolveFor($name, $this);
            }

            return $this->provided[$name];
        }

        throw new UndefinedEntryKeyError($name);
    }

    public function __isset(string $name): bool
    {
        return $name === 'to'
            || \array_key_exists($name, $this->row)
            || $this->ownsAttachment($name)
            || $this->collection?->provides($name) === true;
    }

    /** An entry is written only through attach(). */
    public function __set(string $name, mixed $value): void
    {
        throw new ReadonlyEntryError($name);
    }

    public function __unset(string $name): void
    {
        throw new ReadonlyEntryError($name);
    }

    /** @internal */
    public function ownsAttachment(string $name): bool
    {
        return \array_key_exists($name, $this->values) || isset($this->lazy[$name]);
    }
}
