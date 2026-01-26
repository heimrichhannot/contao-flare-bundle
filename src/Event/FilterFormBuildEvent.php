<?php

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\List\ListDefinition;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Contracts\EventDispatcher\Event;

class FilterFormBuildEvent extends Event
{
    public function __construct(
        public readonly ListDefinition $listDefinition,
        public readonly string         $formName,
        public FormBuilderInterface    $formBuilder,
    ) {}
}