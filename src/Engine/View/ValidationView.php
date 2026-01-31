<?php

namespace HeimrichHannot\FlareBundle\Engine\View;

use Contao\Model;

class ValidationView implements ViewInterface
{
    use Trait\HandlesModelsTrait;
    use Trait\LinksToReaderTrait;

    private array $entriesById;
    private array $entriesByAutoItem;

    /**
     * @param \Closure(int $id): array $fetchEntryById
     * @param \Closure(string $autoItem): array $fetchEntryByAutoItem
     * @param \Closure(Model $model): ?string $readerUrlGenerator
     * @param string $table
     * @param string $autoItemField
     */
    public function __construct(
        private readonly \Closure $fetchEntryById,
        private readonly \Closure $fetchEntryByAutoItem,
        private readonly \Closure $readerUrlGenerator,
        private readonly string   $table,
        private readonly string   $autoItemField,
    ) {}

    public function getEntry(int $id): ?array
    {
        return $this->entriesById[$id] ??= ($this->fetchEntryById)($id);
    }

    public function getEntryByAutoItem(string $autoItem): ?array
    {
        return $this->entriesByAutoItem[$autoItem] ??= ($this->fetchEntryByAutoItem)($autoItem);
    }

    public function getModel(int $id): ?Model
    {
        return $this->fetchModel($this->table, $id, $this->getEntry(...));
    }

    public function getModelByAutoItem(string $autoItem): ?Model
    {
        return $this->fetchModel($this->table, $autoItem, $this->getEntryByAutoItem(...), $this->autoItemField);
    }

    protected function getReaderModel(int $id): ?Model
    {
        return $this->getModel($id);
    }

    protected function getReaderUrlGenerator(): callable
    {
        return $this->readerUrlGenerator;
    }
}