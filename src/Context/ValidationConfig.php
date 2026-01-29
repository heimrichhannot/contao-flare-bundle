<?php

namespace HeimrichHannot\FlareBundle\Context;

use Contao\PageModel;
use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;
use Symfony\Component\Validator\Constraints as Assert;

readonly class ValidationConfig implements ContextConfigInterface, Interface\ReaderLinkableInterface, Interface\PaginatedContextInterface
{
    private PaginatorConfig $paginatorConfig;

    public static function getContextType(): string
    {
        return 'validation';
    }

    /**
     * @param null|\Closure(): array $entryCache
     */
    public function __construct(
        private ?\Closure                   $entryCache = null,
        #[Assert\PositiveOrZero] public int $jumpToReaderPageId = 0,
        #[Assert\NotBlank] private string   $autoItemField = 'id',
        private array                       $filterValues = [],
    ) {
        $this->paginatorConfig = new PaginatorConfig(itemsPerPage: 1);
    }

    public function getEntryCache(): array
    {
        if (!\is_callable($this->entryCache)) {
            return [];
        }

        // Closure return value MUST NOT be cached locally, as it may change during runtime,
        // e.g., when used with InteractiveProjection, entries are only available after a lazy fetch.
        return \is_array($cache = ($this->entryCache)()) ? $cache : [];
    }

    public function getAutoItemField(): string
    {
        return $this->autoItemField;
    }

    public function getJumpToReaderPage(): ?PageModel
    {
        if (!$this->jumpToReaderPageId) {
            return null;
        }

        return PageModel::findByPk($this->jumpToReaderPageId);
    }

    public function getFilterValues(): array
    {
        return $this->filterValues;
    }

    public function getPaginatorConfig(): PaginatorConfig
    {
        return $this->paginatorConfig;
    }

    public function getPaginatorQueryParameter(): ?string
    {
        return null;
    }

    public function withFilterValues(array $values): self
    {
        return new self(
            entryCache: $this->entryCache,
            jumpToReaderPageId: $this->jumpToReaderPageId,
            autoItemField: $this->autoItemField,
            filterValues: $values,
        );
    }
}