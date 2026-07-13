<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Registry;

use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use Psr\Log\LoggerInterface;

/**
 * Resolves the filter element responsible for a filter: an inline instance wins,
 * otherwise the element is looked up in the registry by its type alias.
 */
readonly class FilterElementResolver
{
    public function __construct(
        private FilterElementRegistry $filterElementRegistry,
        private LoggerInterface       $logger,
    ) {}

    public function resolve(Filter $filter): ?FilterElementInterface
    {
        if ($instance = $filter->getElementInstance()) {
            return $instance;
        }

        return $this->resolveType($filter->getElementType(), $filter->source);
    }

    public function resolveType(?string $type, ?string $source = null): ?FilterElementInterface
    {
        $service = $this->filterElementRegistry->get((string) $type)?->getService();

        if (!$service instanceof FilterElementInterface)
        {
            $this->logger->warning(\sprintf(
                '[FLARE] No filter element registered for type "%s" — filter skipped. (%s)',
                $type,
                $source ?: 'filter inlined',
            ));

            return null;
        }

        return $service;
    }
}
