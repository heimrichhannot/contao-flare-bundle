<?php

namespace HeimrichHannot\FlareBundle\View;

use Contao\Model;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use HeimrichHannot\FlareBundle\Trait\FetchModelsTrait;
use Symfony\Component\Form\FormInterface;

class InteractiveView implements ViewInterface
{
    use FetchModelsTrait;

    private array $entries;
    private array $models;

    /**
     * @param Closure(): array $fetchEntries Function to fetch entries lazily.
     * @param FormInterface $form Form to render.
     * @param Paginator $paginator Paginator to render.
     * @param int $totalItems Total number of items.
     */
    public function __construct(
        private readonly \Closure      $fetchEntries,
        private readonly FormInterface $form,
        private readonly Paginator     $paginator,
        private readonly string        $table,
        private readonly int           $totalItems,
    ) {}

    public function getForm(): FormInterface
    {
        return $this->form;
    }

    public function getPaginator(): Paginator
    {
        return $this->paginator;
    }

    public function getCount(): int
    {
        return $this->totalItems;
    }

    public function getEntries(): array
    {
        return $this->entries ??= ($this->fetchEntries)();
    }

    public function getModels(): array
    {
        return $this->models ??= $this->registerModelsFromEntries($this->table, $this->getEntries());
    }

    public function getModel(int $id): ?Model
    {
        return $this->getModels()[$id] ?? null;
    }

    public function isEntriesLoaded(): bool
    {
        return isset($this->entries);
    }
}