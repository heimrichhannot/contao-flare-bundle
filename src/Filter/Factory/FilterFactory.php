<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Factory;

use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterTransformerResolver;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;

/**
 * Creates {@see Filter} DTOs, resolving registered type aliases to their element services.
 */
final readonly class FilterFactory
{
    public function __construct(
        private FilterElementRegistry     $filterElementRegistry,
        private FilterTransformerResolver $filterTransformerResolver,
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
        $type = $this->resolveType($element, $source);
        $element = $this->resolveElement($element, $source);

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

    /**
     * @throws FlareException In case the filter element service cannot be resolved.
     */
    public function createFromFilterModel(
        FilterModel $filterModel
    ): Filter {
        $source = "{$filterModel::getTable()}.{$filterModel->id}";
        $type = $this->resolveType($filterModel->getFilterElementType(), $source);
        $element = $this->resolveElement($type, $source);

        $config = $this->filterTransformerResolver->transform($element, $type, $filterModel) ?? $filterModel->row();

        return new Filter(
            element: $element,
            type: $type,
            config: $config,
            alias: $filterModel->getFilterFormName() ?: "_.{$source}",
            targetAlias: $filterModel->getFilterTargetAlias() ?: null,
            source: $source,
        );
    }

    /**
     * @throws FlareException
     */
    private function resolveType(FilterElementInterface|string $element, ?string $source = null): string
    {
        if (!$type = \is_object($element) ? \get_class($element) : $element)
        {
            throw new FlareException(\sprintf(
                'A filter element instance or registered type alias must be provided%s.',
                $source ? " ($source)" : ""
            ), method: __METHOD__);
        }

        return $type;
    }

    /**
     * @throws FlareException
     */
    private function resolveElement(FilterElementInterface|string $element, ?string $source = null): FilterElementInterface
    {
        if ($element instanceof FilterElementInterface) {
            return $element;
        }

        return $this->filterElementRegistry->getService($element)
            ?? throw new FlareException(\sprintf(
                'Filter element type "%s" not found%s',
                $element,
                $source ? " ($source)" : ""
            ), method: __METHOD__);
    }
}
