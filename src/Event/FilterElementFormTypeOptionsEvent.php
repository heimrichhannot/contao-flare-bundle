<?php

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Form\ChoicesBuilder;
use HeimrichHannot\FlareBundle\Specification\FilterDefinition;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use Symfony\Contracts\EventDispatcher\Event;

class FilterElementFormTypeOptionsEvent extends Event
{
    public function __construct(
        public readonly ChoicesBuilder    $choicesBuilder,
        public readonly ListSpecification $listDefinition,
        public readonly FilterDefinition  $filterDefinition,
        public array                      $options,
    ) {}

    public function getChoicesBuilder(): ChoicesBuilder
    {
        return $this->choicesBuilder;
    }

    public function isChoicesBuilderEnabled(): bool
    {
        return isset($this->choicesBuilder) && $this->choicesBuilder->isEnabled();
    }
}