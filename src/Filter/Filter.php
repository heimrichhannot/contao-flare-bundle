<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\FilterElement\CallbackFilterElement;
use HeimrichHannot\FlareBundle\FilterElement\FilterElementInterface;

/**
 * Immutable runtime representation of a single filter within a list.
 *
 * Pairs a filter element (registered type string or inline instance) with its canonical,
 * element-defined configuration. Contains no DCA/storage specifics — translating a stored
 * row into config is the element's responsibility
 * ({@see \HeimrichHannot\FlareBundle\Contract\FilterElement\ConfigContract}).
 */
final readonly class Filter
{
    /**
     * @param FilterElementInterface|string $element Registered element type alias or an inline element instance.
     * @param array<string, mixed> $config Canonical config (element-defined schema); scalars, arrays, and enums only.
     * @param array<string, mixed>|null $data Runtime data bag, same shape buildFilter() receives.
     *   Submitted form data takes precedence over this bag.
     * @param string|null $alias Form name of the filter. An alias that is not a valid Symfony form
     *   name (e.g. the generated "_.{source}" fallback) never mounts form children.
     * @param string|null $targetAlias Table alias the filter's conditions apply to.
     * @param bool $targetingForced Whether the target alias applies even if the element is not marked as targeted.
     * @param string|null $source Provenance for error messages, e.g. "tl_flare_filter.42".
     */
    public function __construct(
        public FilterElementInterface|string $element,
        public array                         $config = [],
        public ?array                        $data = null,
        public ?string                       $alias = null,
        public ?string                       $targetAlias = null,
        public bool                          $targetingForced = false,
        public ?string                       $source = null,
    ) {}

    public function getElementType(): ?string
    {
        return \is_string($this->element) ? $this->element : null;
    }

    public function getElementInstance(): ?FilterElementInterface
    {
        return $this->element instanceof FilterElementInterface ? $this->element : null;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function withConfig(array $config): self
    {
        return new self($this->element, $config, $this->data, $this->alias, $this->targetAlias, $this->targetingForced, $this->source);
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public function withData(?array $data): self
    {
        return new self($this->element, $this->config, $data, $this->alias, $this->targetAlias, $this->targetingForced, $this->source);
    }

    public function withAlias(?string $alias): self
    {
        return new self($this->element, $this->config, $this->data, $alias, $this->targetAlias, $this->targetingForced, $this->source);
    }

    public function withTargetAlias(?string $targetAlias, bool $forced = true): self
    {
        return new self($this->element, $this->config, $this->data, $this->alias, $targetAlias, !\is_null($targetAlias) && $forced, $this->source);
    }

    public function withSource(?string $source): self
    {
        return new self($this->element, $this->config, $this->data, $this->alias, $this->targetAlias, $this->targetingForced, $source);
    }

    /**
     * Creates an inline filter from closures, without a registered element service.
     *
     * @param callable(FilterBuilderInterface, FilterContext, array<string, mixed>): void $buildFilter
     * @param (callable(\Symfony\Component\Form\FormBuilderInterface, FilterContext): void)|null $buildForm
     */
    public static function fromCallback(
        callable  $buildFilter,
        ?callable $buildForm = null,
        ?string   $alias = null,
        ?string   $targetAlias = null,
    ): self {
        return new self(
            element: new CallbackFilterElement($buildFilter(...), $buildForm ? $buildForm(...) : null),
            alias: $alias,
            targetAlias: $targetAlias,
            targetingForced: !\is_null($targetAlias),
        );
    }

    /**
     * Creates an inline filter that applies a single filter type with the given options —
     * no registered element, no DB row.
     *
     * @param class-string<Type\FilterTypeInterface> $filterTypeClass
     * @param array<string, mixed> $options
     */
    public static function fromType(string $filterTypeClass, array $options = [], ?string $targetAlias = null): self
    {
        return self::fromCallback(
            static function (FilterBuilderInterface $builder) use ($filterTypeClass, $options): void {
                $builder->add($filterTypeClass, $options);
            },
            targetAlias: $targetAlias,
        );
    }

    /**
     * Stable representation for hashing/caching. Inline elements are represented by their
     * class name, which makes hashes of anonymous elements request-local.
     */
    public function fingerprint(): array
    {
        return [
            'element' => $this->getElementType() ?? $this->element::class,
            'config' => $this->config,
            'data' => $this->data,
            'alias' => $this->alias,
            'targetAlias' => $this->targetAlias,
            'targetingForced' => $this->targetingForced,
        ];
    }
}
