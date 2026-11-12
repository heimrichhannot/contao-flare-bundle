<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\ListEntry;

use HeimrichHannot\FlareBundle\Engine\Loader\ValidationLoaderInterface;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Exception\InvalidLookupIdError;
use HeimrichHannot\FlareBundle\List\Entry\Entry;
use HeimrichHannot\FlareBundle\List\Entry\EntryCollection;
use HeimrichHannot\FlareBundle\List\Entry\EntryLookupInterface;
use HeimrichHannot\FlareBundle\List\Entry\EntryTaggerInterface;

/**
 * Looks up member entries of one list by id, by the reader's rules: one query over the list's query without
 * pagination, memoized per normalized id list. Each result is its own sealed collection with its own entries event.
 */
final class EntryLookup implements EntryLookupInterface
{
    /** @var array<string, EntryCollection> keyed by the normalized ids, comma-separated */
    private array $memo = [];

    public function __construct(
        private readonly ValidationLoaderInterface $loader,
        private readonly EntryCollector            $collector,
        private readonly EntryTaggerInterface      $tagger,
    ) {}

    /**
     * @throws InvalidLookupIdError
     * @throws FlareException
     */
    public function lookup(mixed $ids): Entry|EntryCollection|null
    {
        $single = !\is_array($ids);
        $ids = self::normalize($single ? [$ids] : $ids);

        if ($ids === []) {
            return $this->collector->collect([], $this);
        }

        $key = \implode(',', $ids);
        $this->memo[$key] ??= $this->fetch($ids);

        return $single ? $this->memo[$key]->first() : $this->memo[$key];
    }

    /**
     * Validates before anything is coerced: Twig passes `true` or `null` as they are.
     *
     * @param array<mixed> $ids
     * @return list<int> the ids as ints, duplicates collapsed to their first occurrence
     *
     * @throws InvalidLookupIdError
     */
    private static function normalize(array $ids): array
    {
        $normalized = [];

        foreach ($ids as $id) {
            if (!\is_int($id) && !(\is_string($id) && \preg_match('/^\d+$/D', $id) === 1)) {
                throw new InvalidLookupIdError($id);
            }

            $normalized[] = (int) $id;
        }

        return \array_values(\array_unique($normalized));
    }

    /**
     * @param non-empty-list<int> $ids
     *
     * @throws FlareException
     */
    private function fetch(array $ids): EntryCollection
    {
        $byId = [];

        foreach ($this->loader->fetchEntriesByIds($ids) as $row) {
            $byId[(int) $row['id']] = $row;
        }

        $rows = [];

        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $rows[] = $byId[$id];
            }
        }

        $entries = $this->collector->collect($rows, $this);
        $this->tagger->tag(...$entries);

        return $entries;
    }
}
