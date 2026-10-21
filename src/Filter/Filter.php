<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Filter\Control\FilterControlInterface;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;

/**
 * Immutable runtime representation of a single filter within a list.
 *
 * Pairs a filter element instance with its canonical, element-defined configuration and,
 * optionally, a filter control with its own configuration and the filter's value.
 * Contains no DCA/storage specifics — translating a stored source into config is the
 * transformer responsibility of the element and the control
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
     * @param string $alias Form name of the filter and its key within the list. An alias that is not a
     *   valid Symfony form name (e.g. the "_.{type}_{random}" fallback generated for a filter without a
     *   control) never mounts form children.
     * @param array<string, mixed> $config Canonical element config (element-defined schema);
     *   scalars, arrays, and enums only.
     * @param FilterControlInterface|null $control The control presenting the filter, or null for a filter
     *   without user input (intrinsic).
     * @param array<string, mixed> $controlConfig Canonical control config (control-defined schema).
     * @param ValueInterface|null $value A value of the element's value class: the initial value with a
     *   control, the fixed value without one (SPEC_FILTER_FORMS.md §4.2).
     * @param string|null $targetAlias Table alias the filter's conditions apply to.
     * @param bool $targetingForced Whether the target alias applies even if the element is not marked as targeted.
     * @param string|null $source Provenance for error messages, e.g. "tl_flare_filter.42".
     *
     * @internal Use {@see Factory\FilterFactory} to create instances.
     */
    public function __construct(
        public FilterElementInterface  $element,
        public string                  $type,
        public string                  $alias,
        public array                   $config = [],
        public ?FilterControlInterface $control = null,
        public array                   $controlConfig = [],
        public ?ValueInterface         $value = null,
        public ?string                 $targetAlias = null,
        public bool                    $targetingForced = false,
        public ?string                 $source = null,
    ) {}

    public function withValue(?ValueInterface $value): self
    {
        return new self(
            element: $this->element,
            type: $this->type,
            alias: $this->alias,
            config: $this->config,
            control: $this->control,
            controlConfig: $this->controlConfig,
            value: $value,
            targetAlias: $this->targetAlias,
            targetingForced: $this->targetingForced,
            source: $this->source,
        );
    }

    /**
     * @param array<string, mixed> $controlConfig Canonical, already resolved control config.
     */
    public function withControl(?FilterControlInterface $control, array $controlConfig = []): self
    {
        return new self(
            element: $this->element,
            type: $this->type,
            alias: $this->alias,
            config: $this->config,
            control: $control,
            controlConfig: $controlConfig,
            value: $this->value,
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
            alias: $this->alias,
            config: $this->config,
            control: $this->control,
            controlConfig: $this->controlConfig,
            value: $this->value,
            targetAlias: $targetAlias,
            targetingForced: $targetAlias !== null && $forced,
            source: $this->source,
        );
    }

    /**
     * Representation for in-request hashing/caching (SPEC_FILTER_FORMS.md §9).
     *
     * @return array<string, mixed>
     */
    public function fingerprint(): array
    {
        return [
            'element' => $this->element::class,
            'type' => $this->type,
            'alias' => $this->alias,
            'config' => $this->config,
            'control' => $this->control !== null ? $this->control::class : null,
            'controlConfig' => $this->controlConfig,
            'value' => $this->value?->fingerprint(),
            'targetAlias' => $this->targetAlias,
            'targetingForced' => $this->targetingForced,
        ];
    }
}
