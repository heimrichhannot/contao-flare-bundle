<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contao\ListEntry;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DcaExtractor;
use Contao\Model;
use Contao\Model\Collection;
use HeimrichHannot\FlareBundle\Exception\MissingModelRefError;
use HeimrichHannot\FlareBundle\Exception\UndefinedRelationError;
use HeimrichHannot\FlareBundle\List\Entry\Entry;

/**
 * The Contao relations of an entry's columns, as {@see Model::getRelated()} resolves them.
 */
final readonly class EntryRelationResolver
{
    /** @var \WeakMap<Entry, \ArrayObject<string, mixed>> */
    private \WeakMap $resolved;

    public function __construct(
        private ContaoFramework   $framework,
        private EntryModelFactory $modelFactory,
    ) {
        $this->resolved = new \WeakMap();
    }

    /**
     * The related record of a column: a model for a to-one, a collection for a to-many relation, null if there is
     * none. The target table may be any, the list's own included; the entry's membership in the list is not checked.
     *
     * @throws MissingModelRefError for an entry without a modelRef
     * @throws UndefinedRelationError if the table defines no relation for the column or has no model class
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

        $relations = $this->framework->getAdapter(DcaExtractor::class)->getInstance($ref->table)->getRelations();

        if (!isset($relations[$column])) {
            throw new UndefinedRelationError($ref->table, $column);
        }

        $model = $this->modelFactory->create($entry)
            ?? throw new UndefinedRelationError($ref->table, $column, 'belongs to a table without a model class');

        $related = $model->getRelated($column);

        $memo[$column] = $related;

        return $related;
    }
}
