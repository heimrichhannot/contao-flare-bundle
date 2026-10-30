<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\View;

use Contao\Model;
use HeimrichHannot\FlareBundle\Engine\Loader\InteractiveLoaderInterface;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use HeimrichHannot\FlareBundle\Reader\ReaderUrlGeneratorInterface;
use Symfony\Component\Form\FormInterface;

class InteractiveView implements ViewInterface
{
    use HandlesModelsTrait;
    use LinksToReaderTrait;

    private array $entries;
    private array $models;

    /**
     * @param InteractiveLoaderInterface $loader Fetch entries lazily.
     * @param FormInterface $form Form to render.
     * @param Paginator $paginator Paginator to render.
     * @param ReaderUrlGeneratorInterface $readerUrlGenerator Generator for the URL to the reader.
     * @param string $table Table name.
     * @param int $totalItems Total number of items.
     * @param bool $failed Whether the list could not be created; a failed list is not an empty list, templates
     *   render a failure message instead of the entries.
     */
    public function __construct(
        private readonly InteractiveLoaderInterface  $loader,
        private readonly FormInterface               $form,
        private readonly Paginator                   $paginator,
        private readonly ReaderUrlGeneratorInterface $readerUrlGenerator,
        private readonly string                      $table,
        private readonly int                         $totalItems,
        private readonly bool                        $failed = false,
    ) {}

    /**
     * Whether the list could not be created. Twig: `flare_list.failed`.
     */
    public function isFailed(): bool
    {
        return $this->failed;
    }

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
        return $this->entries ??= $this->loader->fetchEntries();
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

    protected function getReaderUrlGenerator(): ReaderUrlGeneratorInterface
    {
        return $this->readerUrlGenerator;
    }
}
