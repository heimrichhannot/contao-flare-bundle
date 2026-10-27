<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;

/**
 * Immutable runtime representation of a single filter within a list.
 *
 * Binds a filter element with its canonical, element-defined configuration and, optionally, a
 * filter control with its own configuration, plus the filter's value.
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
     * @param ElementBinding $element The filter element with its type and config.
     * @param string $alias Form name of the filter and its key within the list. An alias that is not a
     *   valid Symfony form name (e.g. the "_.{type}_{random}" fallback generated for a filter without a
     *   control) never mounts form children.
     * @param ControlBinding|null $control The control presenting the filter with its type and config, or null
     *   for a filter without user input (intrinsic).
     * @param ValueInterface|null $value A value of the element's value class: the initial value with a
     *   control, the fixed value without one (SPEC_FILTER_FORMS.md §4.2).
     * @param string|null $targetAlias Table alias the filter's conditions apply to.
     * @param bool $targetingForced Whether the target alias applies even if the element is not marked as targeted.
     * @param string|null $source Provenance for error messages, e.g. "tl_flare_filter.42".
     *
     * @internal Use {@see Factory\FilterFactory} to create instances.
     */
    public function __construct(
        public ElementBinding  $element,
        public string          $alias,
        public ?ControlBinding $control = null,
        public ?ValueInterface $value = null,
        public ?string         $targetAlias = null,
        public bool            $targetingForced = false,
        public ?string         $source = null,
    ) {}

    public function withValue(?ValueInterface $value): self
    {
        return new self(
            element: $this->element,
            alias: $this->alias,
            control: $this->control,
            value: $value,
            targetAlias: $this->targetAlias,
            targetingForced: $this->targetingForced,
            source: $this->source,
        );
    }

    public function withControl(?ControlBinding $control): self
    {
        return new self(
            element: $this->element,
            alias: $this->alias,
            control: $control,
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
            alias: $this->alias,
            control: $this->control,
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
            'element' => $this->element->component::class,
            'type' => $this->element->type,
            'alias' => $this->alias,
            'config' => $this->element->config->all(),
            'control' => $this->control !== null ? $this->control->component::class : null,
            'controlConfig' => $this->control?->config->all() ?? [],
            'controlType' => $this->control?->type,
            'value' => $this->value?->fingerprint(),
            'targetAlias' => $this->targetAlias,
            'targetingForced' => $this->targetingForced,
        ];
    }
}
