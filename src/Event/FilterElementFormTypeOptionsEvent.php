<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Form\ChoicesBuilder;
use HeimrichHannot\FlareBundle\Specification\ConfiguredFilter;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use Symfony\Contracts\EventDispatcher\Event;

class FilterElementFormTypeOptionsEvent extends Event
{
    public function __construct(
        public readonly ChoicesBuilder    $choicesBuilder,
        public readonly ListSpecification $list,
        public readonly ConfiguredFilter  $filter,
        public array                      $options,
    ) {}
}