<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Exception\ValueClassMismatchError;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\List\Output;

/**
 * Immutable runtime representation of a single filter within a list.
 *
 * Binds a filter element with its canonical, element-defined configuration and, optionally, a
 * filter control with its own configuration, plus the filter's value.
 * Contains no DCA/storage specifics — translating a stored source into config is the
 * transformer responsibility of the element and the control
 * ({@see \HeimrichHannot\FlareBundle\Contract\TransformerContract}).
 *
 * Use {@see Factory\FilterCompoundFactory} to create instances.
 *
 * @api
 */
final readonly class FilterCompound
{
    /**
     * @param ElementBinding $element The filter element with its type and config.
     * @param string $alias Form name of the filter and its key within the list. An alias that is not a
     *   valid Symfony form name (e.g. the "_.{type}_{random}" fallback generated for a filter without a
     *   control) never mounts form children.
     * @param ControlBinding|null $control The control presenting the filter with its type and config, or null
     *   for a filter without user input (intrinsic).
     * @param ValueInterface|null $value A value of the element's value class ({@see ElementBinding::$valueClass}):
     *   the initial value with a control, the fixed value without one.
     * @param string|null $targetAlias Table alias the filter's conditions apply to.
     * @param bool $targetingForced Whether the target alias applies even if the element is not marked as targeted.
     * @param string|null $source Provenance for error messages, e.g. "tl_flare_filter.42".
     * @param list<Output>|null $outputs `null`: the filter is not exclusive and applies to every output. A list: the
     *   filter is exclusive and applies only to these outputs; an empty list means nowhere.
     *
     * @throws ValueClassMismatchError If the value does not match the element's value class — a programming error.
     *
     * @internal Use {@see Factory\FilterCompoundFactory} to create instances.
     */
    public function __construct(
        public ElementBinding  $element,
        public string          $alias,
        public ?ControlBinding $control = null,
        public ?ValueInterface $value = null,
        public ?string         $targetAlias = null,
        public bool            $targetingForced = false,
        public ?string         $source = null,
        public ?array          $outputs = null,
    ) {
        if ($value !== null && ($element->valueClass === null || !$value instanceof $element->valueClass)) {
            throw new ValueClassMismatchError($value, $element->valueClass, $element->type, $source);
        }
    }

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
            outputs: $this->outputs,
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
            outputs: $this->outputs,
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
            outputs: $this->outputs,
        );
    }

    /**
     * @param list<Output>|null $outputs `null` makes the filter not exclusive; see {@see self::$outputs}.
     */
    public function withOutputs(?array $outputs): self
    {
        return new self(
            element: $this->element,
            alias: $this->alias,
            control: $this->control,
            value: $this->value,
            targetAlias: $this->targetAlias,
            targetingForced: $this->targetingForced,
            source: $this->source,
            outputs: $outputs,
        );
    }

    public function isExclusive(): bool
    {
        return $this->outputs !== null;
    }

    /**
     * Whether the filter applies to the output: always when not exclusive, else only when it is one of its outputs.
     */
    public function appliesTo(Output $output): bool
    {
        return $this->outputs === null || \in_array($output, $this->outputs, true);
    }
}
