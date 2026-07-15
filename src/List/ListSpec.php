<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List;

use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\Util\DcaHelper;

/**
 * Immutable runtime representation of a list.
 *
 * Pairs a list type (registered alias or inline instance) with its main data container,
 * its filters, and its canonical, validated configuration. Contains no DCA/storage
 * specifics — translating a stored tl_flare_list row into config is the responsibility
 * of {@see BaseListOptions} and the list type's transformers
 * ({@see \HeimrichHannot\FlareBundle\Contract\TransformerContract}).
 */
final readonly class ListSpec
{
    public string $type;
    public ListDriverInterface $driver;

    /**
     * @param ListDriverReference $reference Registered list type alias and driver.
     * @param string $dc Main data container table of the list.
     * @param array<string, Filter> $filters
     * @param array<string, mixed> $config Canonical config, resolved through the base and type schemas.
     * @param string|null $source Provenance for error messages, e.g. "tl_flare_list.5".
     */
    public function __construct(
        public ListDriverReference $reference,
        public string              $dc,
        public array               $filters = [],
        public array               $config = [],
        public ?string             $source = null,
    ) {
        $this->type = $this->reference->type;
        $this->driver = $this->reference->driver;
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
            reference: $this->reference,
            dc: $this->dc,
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
            reference: $this->reference,
            dc: $this->dc,
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
        return DcaHelper::tryGetColumnName(
            $this->dc,
            (string) ($this->config['fieldAutoItem'] ?? ''),
            DcaHelper::tryGetColumnName($this->dc, 'alias', 'id'),
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
        ]));
    }
}
