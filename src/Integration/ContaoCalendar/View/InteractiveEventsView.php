<?php

namespace HeimrichHannot\FlareBundle\Integration\ContaoCalendar\View;

use HeimrichHannot\FlareBundle\Engine\View\InteractiveView;
use HeimrichHannot\FlareBundle\Integration\ContaoCalendar\GroupsEntriesTrait;

class InteractiveEventsView extends InteractiveView
{
    use GroupsEntriesTrait;

    /**
     * @noinspection MagicMethodsValidityInspection
     * @noinspection PhpMissingParentConstructorInspection
     */
    public function __construct(
        private readonly InteractiveView $inner,
    ) {}

    public function __call(string $name, array $arguments)
    {
        return $this->inner->$name(...$arguments);
    }

    public function getEntriesGrouped(): array
    {
        // todo(@ericges): implement or remove class
        return [];
    }
}