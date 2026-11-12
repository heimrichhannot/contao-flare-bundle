<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contao\ListEntry;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DcaExtractor;
use Contao\Model;
use Contao\Model\Registry;
use HeimrichHannot\FlareBundle\Exception\MissingModelRefError;
use HeimrichHannot\FlareBundle\List\Entry\Entry;

/**
 * The one place an entry becomes a Contao model.
 */
final readonly class EntryModelFactory
{
    /** @var \WeakMap<Entry, Model> */
    private \WeakMap $models;

    public function __construct(
        private ContaoFramework $framework,
    ) {
        $this->models = new \WeakMap();
    }

    /**
     * The model of an entry, memoized per entry: the instance Contao's registry holds for its record, else a new one
     * built from the entry's row, restricted to the columns of the table. A new model never enters the registry (a
     * later `findByPk()` loads the record from the database, not the list's row) and cannot be saved. The returned
     * model is shared by every caller for this entry.
     *
     * Which of the two a caller gets depends on whether the record was registered first; for rows the list query maps
     * (joins, overlays), the rule comes with the query refactor.
     *
     * @return ?Model null if the table has no model class
     * @throws MissingModelRefError for an entry without a modelRef
     */
    public function create(Entry $entry): ?Model
    {
        $ref = $entry->meta->modelRef ?? throw new MissingModelRefError();

        if (isset($this->models[$entry])) {
            return $this->models[$entry];
        }

        $class = $this->modelClass($ref->table);

        if ($class === null) {
            return null;
        }

        $registered = $this->framework->getAdapter(Registry::class)->getInstance()->fetch($ref->table, $ref->id);

        if ($registered instanceof Model) {
            $this->models->offsetSet($entry, $registered);

            return $registered;
        }

        $columns = $this->framework->getAdapter(DcaExtractor::class)->getInstance($ref->table)->getFields();
        $columns['id'] = true;

        // Built without a row: Contao's constructor registers a model it gets a row for.
        $model = $this->framework->createInstance($class);
        $model->setRow(\array_intersect_key($entry->row, $columns));
        $model->preventSaving(false);

        $this->models->offsetSet($entry, $model);

        return $model;
    }

    /**
     * @return ?class-string<Model>
     */
    private function modelClass(string $table): ?string
    {
        try {
            $class = $this->framework->getAdapter(Model::class)->getClassFromTable($table);
        } catch (\RuntimeException) {
            // Contao 5 throws for a table without a $GLOBALS['TL_MODELS'] entry
            return null;
        }

        if (!\is_string($class) || !\class_exists($class) || !\is_subclass_of($class, Model::class)) {
            return null;
        }

        return $class;
    }
}
