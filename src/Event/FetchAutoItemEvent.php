<?php

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Filter\FilterContext;

class FetchAutoItemEvent extends AbstractFetchEvent
{
    public function __construct(
        private int|string     $autoItem,
        private ?FilterContext $autoItemFilterContext,
                              ...$args
    ) {
        parent::__construct(...$args);
    }

    public function getAutoItem(): int
    {
        return $this->autoItem;
    }

    public function setAutoItem(int|string $autoItem): void
    {
        $this->autoItem = $autoItem;
    }

    public function getAutoItemFilterContext(): ?FilterContext
    {
        return $this->autoItemFilterContext;
    }

    public function setAutoItemFilterContext(?FilterContext $autoItemFilterContext): void
    {
        $this->autoItemFilterContext = $autoItemFilterContext;
    }

    public function getEventName(): string
    {
        return "flare.fetch_auto_item";
    }
}