<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contao\ListEntry;

use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\DcaExtractor;
use Contao\FilesModel;
use Contao\Model;
use Contao\Model\Collection;
use Contao\StringUtil;
use HeimrichHannot\FlareBundle\Exception\MissingModelRefError;
use HeimrichHannot\FlareBundle\Exception\UndefinedRelationError;
use HeimrichHannot\FlareBundle\List\Entry\Entry;

/**
 * The Contao relations of an entry's columns, resolved the way {@see Model::getRelated()} resolves a lazy relation,
 * without a model of the entry: the relation from the entry table's DCA, the value from the entry's row, the records
 * through the finders of the related table's model class.
 */
final readonly class EntryRelationResolver
{
    /** Contao's `empty()`: the relation has no reference yet. */
    private const NO_VALUE = [null, false, 0, 0.0, '', '0', []];

    /** @var \WeakMap<Entry, \ArrayObject<string, mixed>> */
    private \WeakMap $resolved;

    public function __construct(
        private ContaoFramework $framework,
    ) {
        $this->resolved = new \WeakMap();
    }

    /**
     * The related record of a column: a model for a to-one, a collection for a to-many relation, null if there is
     * none. The target table may be any, the list's own included; the entry's membership in the list is not checked.
     *
     * @throws MissingModelRefError for an entry without a modelRef
     * @throws UndefinedRelationError for a column without relation, a related table without a model class or an
     *                                unknown relation type
     */
    public function resolve(Entry $entry, string $column): Model|Collection|null
    {
        $ref = $entry->meta->modelRef ?? throw new MissingModelRefError();

        if (!$this->resolved->offsetExists($entry)) {
            $this->resolved->offsetSet($entry, new \ArrayObject());
        }

        $memo = $this->resolved->offsetGet($entry);

        if ($memo->offsetExists($column)) {
            return $memo[$column];
        }

        $related = $this->load($ref->table, $column, $entry->row[$column] ?? null);

        $memo[$column] = $related;

        return $related;
    }

    private function load(string $table, string $column, mixed $value): Model|Collection|null
    {
        $relations = $this->framework->getAdapter(DcaExtractor::class)->getInstance($table)->getRelations();
        $relation = $relations[$column] ?? throw new UndefinedRelationError($table, $column);

        if (\in_array($value, self::NO_VALUE, true)) {
            return null;
        }

        $finder = $this->framework->getAdapter($this->relatedClass($relation['table'], $column, $table));

        return match ($relation['type'] ?? null) {
            'hasOne', 'belongsTo' => $finder->findOneBy($relation['field'], $value),
            'hasMany', 'belongsToMany' => $this->findMany($relation, $value, $finder),
            default => throw new UndefinedRelationError(
                $table,
                $column,
                \sprintf('has the unsupported relation type "%s"', $relation['type'] ?? ''),
            ),
        };
    }

    /**
     * The records of a to-many relation in the stored order; the values are bound as parameters, also in the order.
     *
     * @param array<string, mixed> $relation
     * @param Adapter<Model>       $finder
     */
    private function findMany(array $relation, mixed $value, Adapter $finder): ?Collection
    {
        $values = $this->toManyValues($relation, $value);

        if ([] === $values) {
            return null;
        }

        if ('tl_files' === $relation['table'] && 'uuid' === $relation['field']) {
            return $this->framework->getAdapter(FilesModel::class)->findMultipleByUuids($values);
        }

        $quoted = $this->framework->getAdapter(Database::class)->quoteIdentifier($relation['field']);
        $field = $relation['table'] . '.' . $quoted;
        $placeholders = \implode(',', \array_fill(0, \count($values), '?'));

        return $finder->findBy(
            ["{$field} IN({$placeholders})"],
            [...$values, \implode(',', $values)],
            ['order' => "FIND_IN_SET({$field}, ?)"],
        );
    }

    /**
     * @param array<string, mixed> $relation
     *
     * @return list<mixed>
     */
    private function toManyValues(array $relation, mixed $value): array
    {
        $values = isset($relation['delimiter'])
            ? StringUtil::trimsplit($relation['delimiter'], $value)
            : StringUtil::deserialize($value, true);

        return \array_values($values);
    }

    /**
     * @return class-string<Model>
     */
    private function relatedClass(string $table, string $column, string $ownTable): string
    {
        try {
            $class = $this->framework->getAdapter(Model::class)->getClassFromTable($table);
        } catch (\RuntimeException) {
            // Contao 5 throws for a table without a $GLOBALS['TL_MODELS'] entry
            $class = null;
        }

        if (!\is_string($class) || !\class_exists($class) || !\is_subclass_of($class, Model::class)) {
            throw new UndefinedRelationError($ownTable, $column, 'relates to a table without a model class');
        }

        return $class;
    }
}
