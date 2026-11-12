<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\View;

use HeimrichHannot\FlareBundle\Engine\ListEntry\EntryCollector;
use HeimrichHannot\FlareBundle\Engine\Loader\InteractiveLoaderInterface;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\List\Entry\EntryCollection;
use HeimrichHannot\FlareBundle\List\Entry\EntryLookupInterface;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use Symfony\Component\Form\FormInterface;

class InteractiveView implements ViewInterface
{
    private EntryCollection $entries;

    /**
     * @param InteractiveLoaderInterface $loader Fetch the rows of the page lazily.
     * @param FormInterface $form Form to render.
     * @param Paginator $paginator Paginator to render.
     * @param EntryCollector $collector Turns the rows into entries that link to the reader.
     * @param EntryLookupInterface $lookup Member entries of the list by the reader's rules, for the entries event.
     * @param int $totalItems Total number of items.
     * @param bool $failed Whether the list could not be created; a failed list is not an empty list, templates
     *   render a failure message instead of the entries.
     */
    public function __construct(
        private readonly InteractiveLoaderInterface $loader,
        private readonly FormInterface              $form,
        private readonly Paginator                  $paginator,
        private readonly EntryCollector             $collector,
        private readonly EntryLookupInterface       $lookup,
        private readonly int                        $totalItems,
        private readonly bool                       $failed = false,
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

    /**
     * The total number of items (for the pagination); `flare_list.entries|length` is the page's count.
     */
    public function getCount(): int
    {
        return $this->totalItems;
    }

    /**
     * The entries of the page, loaded on the first call: the entries event ran (unless there are none), the
     * collection is sealed. Twig: `flare_list.entries`.
     *
     * @throws FlareException|FilterException
     */
    public function getEntries(): EntryCollection
    {
        return $this->entries ??= $this->collector->collect($this->loader->fetchEntries(), $this->lookup);
    }

    public function issetEntries(): bool
    {
        return isset($this->entries);
    }
}
