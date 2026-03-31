<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\View;

use Contao\Model;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use Symfony\Component\Form\FormInterface;

class InteractiveView implements ViewInterface
{
    use HandlesModelsTrait;
    use LinksToReaderTrait;

    private array $entries;
    private array $models;

    /**
     * @param Closure(): array $fetchEntries Function to fetch entries lazily.
     * @param FormInterface $form Form to render.
     * @param Paginator $paginator Paginator to render.
     * @param Closure(Model): ?string $readerUrlGenerator Function to generate the URL for a reader.
     * @param string $table Table name.
     * @param int $totalItems Total number of items.
     */
    public function __construct(
        private readonly \Closure      $fetchEntries,
        private readonly FormInterface $form,
        private readonly Paginator     $paginator,
        private readonly \Closure      $readerUrlGenerator,
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

    public function issetEntries(): bool
    {
        return isset($this->entries);
    }

    /**
     * @throws FlareException
     */
    public function getModels(): array
    {
        return $this->models ??= $this->createModelsFromEntries($this->table, $this->getEntries());
    }

    /**
     * @throws FlareException
     */
    public function getModel(int $id): ?Model
    {
        return $this->getModels()[$id] ?? null;
    }

    /**
     * @throws FlareException
     */
    protected function getReaderModel(int $id): ?Model
    {
        return $this->getModel($id);
    }

    protected function getReaderUrlGenerator(): callable
    {
        return $this->readerUrlGenerator;
    }
}