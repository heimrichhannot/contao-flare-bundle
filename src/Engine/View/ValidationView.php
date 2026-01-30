<?php

namespace HeimrichHannot\FlareBundle\Engine\View;

use Contao\Model;

class ValidationView implements ViewInterface
{
    use Trait\HandlesModelsTrait;
    use Trait\LinksToReaderTrait;

    private array $entries;

    /**
     * @param \Closure(int $id): array $fetchEntry
     * @param \Closure(Model $model): ?string $readerUrlGenerator
     * @param string $table
     */
    public function __construct(
        private readonly \Closure $fetchEntry,
        private readonly \Closure $readerUrlGenerator,
        private readonly string   $table,
    ) {}

    public function getEntry(int $id): ?array
    {
        return $this->entries[$id] ??= ($this->fetchEntry)($id);
    }

    public function getModel(int $id): ?Model
    {
        return $this->fetchModel($this->table, $id, $this->getEntry(...));
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