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
    public function __construct(
        private ContaoFramework $framework,
    ) {}

    /**
     * The model of an entry: the instance Contao's registry holds for its record, else a new one built from the
     * entry's row, restricted to the columns of the table.
     *
     * @return ?Model null if the table has no model class
     * @throws MissingModelRefError for an entry without a modelRef
     */
    public function create(Entry $entry): ?Model
    {
        $ref = $entry->meta->modelRef ?? throw new MissingModelRefError();

        $class = $this->modelClass($ref->table);

        if ($class === null) {
            return null;
        }

        $registered = $this->framework->getAdapter(Registry::class)->getInstance()->fetch($ref->table, $ref->id);

        if ($registered instanceof Model) {
            return $registered;
        }

        $columns = $this->framework->getAdapter(DcaExtractor::class)->getInstance($ref->table)->getFields();
        $columns['id'] = true;

        return $this->framework->createInstance($class, [\array_intersect_key($entry->row, $columns)]);
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
