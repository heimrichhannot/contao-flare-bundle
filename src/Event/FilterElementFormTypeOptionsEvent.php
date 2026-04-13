<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Form\ChoicesBuilder;
use HeimrichHannot\FlareBundle\Specification\FilterDefinition;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use Symfony\Contracts\EventDispatcher\Event;

class FilterElementFormTypeOptionsEvent extends Event
{
    public function __construct(
        public readonly ChoicesBuilder    $choicesBuilder,
        public readonly ListSpecification $list,
        public readonly FilterDefinition  $filter,
        public array                      $options,
    ) {}
}