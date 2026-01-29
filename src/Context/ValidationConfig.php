<?php

namespace HeimrichHannot\FlareBundle\Context;

use Contao\PageModel;
use Symfony\Component\Validator\Constraints as Assert;

readonly class ValidationConfig implements ContextConfigInterface, Interface\ReaderLinkableInterface
{
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
    ) {}

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
}