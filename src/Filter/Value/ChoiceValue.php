<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Value;

/**
 * A selection from a server-provided option set: the value consumed by
 * `FieldValueChoiceFilterElement`, `DcaSelectFieldFilterElement` and
 * `CodefogTagsChoiceFilterElement`.
 *
 * Keys, not domain values. Choice keys are strings by construction (Symfony view data always is)
 * and their *meaning* is element-defined, so any further interpretation — the `(int)` cast for tag
 * ids, the `LOWER(TRIM())` folding `FieldValueChoiceFilterType` expects, dropping
 * {@see \HeimrichHannot\FlareBundle\Form\ChoicesBuilder::EMPTY_CHOICE} — belongs in the element's
 * `valueFromChoiceKeys()`, not here (SPEC_FILTER_FORMS.md §3.4). In particular the lowercasing is
 * coupled to that one filter type and would break `DcaSelectFilterType`'s case-sensitive lookup of
 * DCA option keys.
 *
 * Sorted, because all three consuming filter types emit `IN()` and therefore do not care about
 * order (§9). Deliberately distinct from {@see KeywordsValue} despite the identical shape: §4.3 —
 * two elements share a value object only if every form registered for one is meaningful for the
 * other, and a choice form is not meaningful for a free-text search.
 */
final readonly class ChoiceValue
{
    /** @var list<string> Non-empty, deduplicated, ascending by string comparison. */
    public array $keys;

    /**
     * @param array<array-key, mixed> $keys Submitted choice keys. Entries with no string
     *   representation, and empty strings, are dropped.
     */
    public function __construct(array $keys)
    {
        $strings = [];

        foreach ($keys as $key)
        {
            if ($key === null || \is_bool($key) || \is_array($key)) {
                continue;
            }

            if (\is_object($key) && !$key instanceof \Stringable) {
                continue;
            }

            if (($key = (string) $key) !== '') {
                $strings[] = $key;
            }
        }

        $strings = \array_values(\array_unique($strings, \SORT_STRING));

        \sort($strings, \SORT_STRING);

        $this->keys = $strings;
    }

    /**
     * @param array<array-key, mixed> $keys
     */
    public static function tryFrom(array $keys): ?self
    {
        $value = new self($keys);

        return $value->keys === [] ? null : $value;
    }
}
