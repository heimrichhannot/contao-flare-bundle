<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Resolver;

use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use Psr\Log\LoggerInterface;

/**
 * Resolves the filter element responsible for a filter by looking up its type alias
 * in the registry.
 */
final readonly class FilterElementResolver
{
    public function __construct(
        private FilterElementRegistry $filterElementRegistry,
        private LoggerInterface       $logger,
    ) {}

    public function resolve(Filter $filter): ?FilterElementInterface
    {
        return $this->resolveType($filter->type, $filter->source);
    }

    public function resolveType(?string $type, ?string $source = null): ?FilterElementInterface
    {
        $service = $this->filterElementRegistry->get((string) $type)?->getService();

        if (!$service instanceof FilterElementInterface)
        {
            $this->logger->warning(\sprintf(
                '[FLARE] No filter element registered for type "%s" — filter skipped. (%s)',
                $type,
                $source ?: 'no source',
            ));

            return null;
        }

        return $service;
    }
}
