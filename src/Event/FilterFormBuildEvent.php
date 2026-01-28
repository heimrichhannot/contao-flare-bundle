<?php

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Contracts\EventDispatcher\Event;

class FilterFormBuildEvent extends Event
{
    public function __construct(
        public readonly ListSpecification $listSpecification,
        public readonly string            $formName,
        public FormBuilderInterface       $formBuilder,
    ) {}
}