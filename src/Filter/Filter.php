<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;

/**
 * Immutable runtime representation of a single filter within a list.
 *
 * Pairs a filter element instance with its canonical, element-defined configuration.
 * Contains no DCA/storage specifics — translating a stored source into config is the
 * element's transformer responsibility
 * ({@see \HeimrichHannot\FlareBundle\Contract\TransformerContract}).
 *
 * Use {@see Factory\FilterFactory} to create instances.
 *
 * @api
 */
final readonly class Filter
{
    /**
     * @param FilterElementInterface $element Filter element service (registered or inline).
     * @param string $type Registered element type alias. Only used for named event dispatch
     *   (`flare.filter_element.{type}.*`) and targeting lookups.
     * @param array<string, mixed> $config Canonical config (element-defined schema); scalars, arrays, and enums only.
     * @param FilterData|null $data Programmatically set runtime data, same as buildFilter()
     *   receives. Submitted form data takes precedence over it.
     * @param string|null $alias Form name of the filter. An alias that is not a valid Symfony form
     *   name (e.g. the generated "_.{source}" fallback) never mounts form children.
     * @param string|null $targetAlias Table alias the filter's conditions apply to.
     * @param bool $targetingForced Whether the target alias applies even if the element is not marked as targeted.
     * @param string|null $source Provenance for error messages, e.g. "tl_flare_filter.42".
     *
     * @internal Use {@see Factory\FilterFactory} to create instances.
     */
    public function __construct(
        public FilterElementInterface $element,
        public string                 $type,
        public array                  $config = [],
        public ?FilterData            $data = null,
        public ?string                $alias = null,
        public ?string                $targetAlias = null,
        public bool                   $targetingForced = false,
        public ?string                $source = null,
    ) {}

    public function withData(?FilterData $data): self
    {
        return new self(
            element: $this->element,
            type: $this->type,
            config: $this->config,
            data: $data,
            alias: $this->alias,
            targetAlias: $this->targetAlias,
            targetingForced: $this->targetingForced,
            source: $this->source,
        );
    }

    public function withAlias(?string $alias): self
    {
        return new self(
            element: $this->element,
            type: $this->type,
            config: $this->config,
            data: $this->data,
            alias: $alias,
            targetAlias: $this->targetAlias,
            targetingForced: $this->targetingForced,
            source: $this->source,
        );
    }

    public function withTargetAlias(?string $targetAlias, bool $forced = true): self
    {
        return new self(
            element: $this->element,
            type: $this->type,
            config: $this->config,
            data: $this->data,
            alias: $this->alias,
            targetAlias: $targetAlias,
            targetingForced: !\is_null($targetAlias) && $forced,
            source: $this->source,
        );
    }

    /**
     * Stable representation for hashing/caching.
     */
    public function fingerprint(): array
    {
        return [
            'element' => \get_class($this->element),
            'type' => $this->type,
            'config' => $this->config,
            'data' => $this->data?->toArray(),
            'alias' => $this->alias,
            'targetAlias' => $this->targetAlias,
            'targetingForced' => $this->targetingForced,
        ];
    }
}
