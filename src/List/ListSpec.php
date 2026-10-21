<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List;

use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\Util\DcaHelper;

/**
 * Immutable runtime representation of a list.
 *
 * Pairs a list driver (registered service or inline instance) with its filters and its
 * canonical, validated configuration. Contains no DCA/storage specifics — translating a
 * stored tl_flare_list row into config is the responsibility of {@see BaseListOptions}
 * and the driver's transformers
 * ({@see \HeimrichHannot\FlareBundle\Contract\TransformerContract}).
 *
 * Use {@see Factory\ListSpecFactory} to create instances — it resolves the config schema
 * and guarantees a well-defined data container.
 *
 * @api
 */
final readonly class ListSpec
{
    /**
     * @param ListDriverInterface $driver List driver service (registered or inline).
     * @param array<string, Filter> $filters Keyed by alias.
     * @param array<string, mixed> $config Canonical config, resolved through the base and driver schemas.
     * @param string|null $source Provenance for error messages, e.g. "tl_flare_list.5".
     * @param list<string> $failures Failures of filters without a control that could not be built; a list
     *   with failures has failed as a whole (SPEC_FILTER_FORMS.md §8.1).
     *
     * @internal Use {@see Factory\ListSpecFactory} to create instances.
     */
    public function __construct(
        public ListDriverInterface $driver,
        public string              $type,
        public string              $dc,
        public array               $filters = [],
        public array               $config = [],
        public ?string             $source = null,
        public array               $failures = [],
    ) {}

    /**
     * Adds a filter under its alias.
     *
     * A filter of the same alias, or one stored under that alias as its key, is never replaced
     * silently: replacing a filter without a control would widen the list (SPEC_FILTER_FORMS.md
     * §8.1). Remove it first with {@see withoutFilter()}.
     *
     * @throws FlareException If the alias is already taken by a filter or a filter's key.
     */
    public function withFilter(Filter $filter): self
    {
        $taken = isset($this->filters[$filter->alias]) || \array_filter(
            $this->filters,
            static fn (Filter $existing): bool => $existing->alias === $filter->alias,
        );

        if ($taken)
        {
            throw new FlareException(
                \sprintf('The list already has a filter with the alias "%s".', $filter->alias),
                method: __METHOD__,
                source: $filter->source ?? $this->source,
            );
        }

        return $this->withFilters([...$this->filters, $filter->alias => $filter]);
    }

    public function withoutFilter(Filter|string $filter_or_class_or_alias): self
    {
        $filters = \array_filter(
            $this->filters,
            static fn (Filter $existingFilter): bool =>
                $existingFilter !== $filter_or_class_or_alias
                && $existingFilter->alias !== $filter_or_class_or_alias
                && \get_class($existingFilter->element) !== $filter_or_class_or_alias,
        );

        return $this->withFilters($filters);
    }

    /**
     * @param array<string, Filter> $filters
     */
    public function withFilters(array $filters): self
    {
        return new self(
            driver: $this->driver,
            type: $this->type,
            dc: $this->dc,
            filters: $filters,
            config: $this->config,
            source: $this->source,
            failures: $this->failures,
        );
    }

    public function withFailure(string $failure): self
    {
        return new self(
            driver: $this->driver,
            type: $this->type,
            dc: $this->dc,
            filters: $this->filters,
            config: $this->config,
            source: $this->source,
            failures: [...$this->failures, $failure],
        );
    }

    /**
     * @param class-string<FilterElementInterface> $class
     */
    public function hasFilterInstance(string $class): bool
    {
        foreach ($this->filters as $filter)
        {
            if ($filter->element instanceof $class) {
                return true;
            }
        }

        return false;
    }

    public function getAutoItemField(): string
    {
        $dc = $this->dc;

        return DcaHelper::tryGetColumnName(
            $dc,
            (string) ($this->config['fieldAutoItem'] ?? ''),
            DcaHelper::tryGetColumnName($dc, 'alias', 'id'),
        );
    }

    public function hash(): string
    {
        return \sha1(\serialize([
            \get_class($this->driver),
            $this->type,
            $this->dc,
            $this->source,
            $this->config,
            \array_map(static fn (Filter $filter): array => $filter->fingerprint(), $this->filters),
            $this->failures,
        ]));
    }
}
