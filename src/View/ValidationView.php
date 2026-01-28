<?php

namespace HeimrichHannot\FlareBundle\View;

use Contao\Model;
use HeimrichHannot\FlareBundle\Trait\FetchModelsTrait;

class ValidationView implements ViewInterface
{
    use FetchModelsTrait;

    private array $entries;

    /**
     * @param array|null $entries
     * @param \Closure(int $id): array $fetchEntry
     * @param string $table
     */
    public function __construct(
        private readonly \Closure $fetchEntry,
        private readonly string   $table,
    ) {}

    public function getEntry(int $id): ?array
    {
        return $this->entries[$id] ??= ($this->fetchEntry)($id);
    }

    public function getModel(int $id): Model
    {
        return $this->fetchModel($this->table, $id, $this->getEntry(...));
    }
}