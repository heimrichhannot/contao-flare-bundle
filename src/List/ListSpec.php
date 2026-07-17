<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List;

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
 */
final readonly class ListSpec
{
    /**
     * The main data container table of the list.
     */
    public string $dc;

    /**
     * @param ListDriverInterface $driver List driver service (registered or inline).
     * @param array<string, Filter> $filters
     * @param array<string, mixed> $config Canonical config, resolved through the base and driver schemas.
     * @param string|null $source Provenance for error messages, e.g. "tl_flare_list.5".
     */
    public function __construct(
        public ListDriverInterface $driver,
        public array               $filters = [],
        public array               $config = [],
        public ?string             $source = null,
    ) {
        $this->dc = (string) ($this->config['dc'] ?? '');
    }

    /**
     * Adds a filter. The key defaults to the filter's alias; alias-less filters receive a generated key.
     */
    public function withFilter(Filter $filter, ?string $key = null): self
    {
        if (null === ($key ??= $filter->alias))
        {
            $index = 0;

            while (isset($this->filters["_generated_{$index}"])) {
                $index++;
            }

            $key = "_generated_{$index}";
        }

        return $this->withFilters([...$this->filters, $key => $filter]);
    }

    public function withoutFilter(string $key): self
    {
        $filters = $this->filters;
        unset($filters[$key]);

        return $this->withFilters($filters);
    }

    /**
     * @param array<string, Filter> $filters
     */
    public function withFilters(array $filters): self
    {
        return new self(
            driver: $this->driver,
            filters: $filters,
            config: $this->config,
            source: $this->source,
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    public function withConfig(array $config): self
    {
        return new self(
            driver: $this->driver,
            filters: $this->filters,
            config: $config,
            source: $this->source,
        );
    }

    public function hasFilterOfType(string $elementType): bool
    {
        foreach ($this->filters as $filter)
        {
            if ($filter->type === $elementType) {
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
            $this->source,
            $this->config,
            \array_map(static fn (Filter $filter): array => $filter->fingerprint(), $this->filters),
        ]));
    }
}
