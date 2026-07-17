<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Factory;

use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;

/**
 * Creates {@see Filter} DTOs, resolving registered type aliases to their element services.
 */
final readonly class FilterFactory
{
    public function __construct(
        private FilterElementRegistry $filterElementRegistry,
    ) {}

    /**
     * @param FilterElementInterface|string $element Filter element instance or registered type alias.
     * @param array<string, mixed> $config
     * @param array<string, mixed>|null $data
     *
     * @throws FlareException In case no filter element is registered under the given type alias.
     *
     * @see Filter::__construct for the remaining parameters.
     */
    public function create(
        FilterElementInterface|string $element,
        array                         $config = [],
        ?array                        $data = null,
        ?string                       $alias = null,
        ?string                       $targetAlias = null,
        bool                          $targetingForced = false,
        ?string                       $source = null,
    ): Filter {
        $type = null;

        if (\is_string($element))
        {
            $type = $element;

            $element = $this->filterElementRegistry->getService($type)
                ?? throw new FlareException(\sprintf('Filter element type "%s" not found', $type));
        }

        return new Filter(
            element: $element,
            type: $type,
            config: $config,
            data: $data,
            alias: $alias,
            targetAlias: $targetAlias,
            targetingForced: $targetingForced,
            source: $source,
        );
    }
}
