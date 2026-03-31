<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Query;

use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

readonly class ListQueryConfig
{
    public function __construct(
        public ListSpecification $list,
        public ContextInterface  $context,
        public array             $filterValues,
        public bool              $isCounting = false,
        public bool              $onlyId = false,
        public array             $attributes = [],
    ) {}

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