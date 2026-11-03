<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Query;

use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\List\ListSpec;

readonly class ListQueryConfig
{
    /**
     * @param array<string, ?ValueInterface> $filterValues Filter values keyed by filter alias.
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        public ListSpec         $list,
        public ContextInterface $context,
        public array            $filterValues,
        public bool             $isCounting = false,
        public bool             $onlyId = false,
        public array            $attributes = [],
    ) {}

    /**
     * @param array<string, ?ValueInterface>|null $filterValues
     * @param array<string, mixed>|null $attributes
     */
    public function with(
        ?array $filterValues = null,
        ?bool  $isCounting = null,
        ?bool  $onlyId = null,
        ?array $attributes = null,
    ): self {
        return new self(
            list: $this->list,
            context: $this->context,
            filterValues: $filterValues ?? $this->filterValues,
            isCounting: $isCounting ?? $this->isCounting,
            onlyId: $onlyId ?? $this->onlyId,
            attributes: $attributes ?? $this->attributes,
        );
    }
}
