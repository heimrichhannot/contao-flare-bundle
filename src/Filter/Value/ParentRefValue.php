<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Value;

/**
 * A selection of parent records, grouped by parent table.
 */
final readonly class ParentRefValue implements ValueInterface
{
    /**
     * Parent ids grouped by parent table. Tables are sorted by name, ids sorted ascending,
     * and are deduplicated and positive-only; a table with no surviving id is dropped entirely.
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
