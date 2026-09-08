<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Value;

/**
 * A selection of parent records, grouped by parent table: the value consumed by
 * `ArchiveFilterElement`.
 *
 * The shape is `BelongsToRelationFilterType`'s `submitted_data`, documented verbatim at
 * `BelongsToRelationFilterElement.php:107-118`. One value class spans both ptable modes
 * (SPEC_FILTER_FORMS.md §7.4): the static main-ptable mode is the single-table degenerate case, and
 * `buildFilter()` re-derives the flat vs. grouped filter-type call from `PtableInferrer` exactly as
 * it does today.
 *
 * Ids, never `Contao\Model` instances: a model carries `$arrData` *and* `$arrModified`, so an
 * unrelated mutation moves the hash (§9, measured in
 * tests/Filter/ValueObjectSerializeProbeTest.php).
 *
 * **"Use the full whitelist" is the absence of this value, not a state of it.** Today
 * `ArchiveFilterElement::processRuntimeValue()` already treats "nothing submitted" (null), "the
 * empty option was chosen" (true) and "no model survived" ([]) identically, so nothing is lost by
 * collapsing them to `null` — and the alternative, a `wholeWhitelist` flag, would let the form
 * decide which rows match (§3.4, §4.1) while creating a second representation of "nothing
 * selected" (§9). {@see tryFrom()} is the guard that keeps an empty instance from existing.
 */
final readonly class ParentRefValue
{
    /**
     * Parent ids grouped by parent table. Tables sorted by name, ids sorted ascending,
     * deduplicated and positive-only; a table with no surviving id is dropped entirely.
     *
     * @var array<string, list<int>>
     */
    public array $parents;

    /**
     * @param array<array-key, mixed> $parents Table name => iterable of ids. Non-string keys,
     *   non-iterable values, non-numeric ids and ids <= 0 are dropped.
     */
    public function __construct(array $parents)
    {
        /** @var array<string, array<int, true>> $seen */
        $seen = [];

        foreach ($parents as $table => $ids)
        {
            // A numeric-looking array key would have been cast to int by PHP, and no table name
            // can look like that, so a non-string key is garbage.
            if (!\is_string($table) || ($table = \trim($table)) === '') {
                continue;
            }

            if (!\is_iterable($ids)) {
                continue;
            }

            foreach ($ids as $id)
            {
                if (!\is_int($id) && !\is_float($id) && !(\is_string($id) && \is_numeric($id))) {
                    continue;
                }

                if (($id = (int) $id) > 0) {
                    $seen[$table][$id] = true;
                }
            }
        }

        $normalized = [];

        foreach ($seen as $table => $ids)
        {
            $ids = \array_keys($ids);

            \sort($ids, \SORT_NUMERIC);

            $normalized[$table] = $ids;
        }

        \ksort($normalized, \SORT_STRING);

        $this->parents = $normalized;
    }

    /**
     * @param array<array-key, mixed> $parents
     */
    public static function tryFrom(array $parents): ?self
    {
        $value = new self($parents);

        return $value->parents === [] ? null : $value;
    }
}
