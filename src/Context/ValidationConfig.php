<?php

namespace HeimrichHannot\FlareBundle\Context;

readonly class ValidationConfig implements ContextConfigInterface
{
    /**
     * @param null|\Closure(): array $entryCache
     */
    public function __construct(
        private ?\Closure $entryCache = null,
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
}