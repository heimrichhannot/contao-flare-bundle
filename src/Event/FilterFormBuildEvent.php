<?php

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Filter\FilterContextCollection;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Contracts\EventDispatcher\Event;

class FilterFormBuildEvent extends Event
{
    public function __construct(
        public readonly FilterContextCollection $filters,
        public readonly string                  $formName,
        public FormBuilderInterface             $formBuilder,
    ) {}
}