<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\List\ListSpec;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Contracts\EventDispatcher\Event;

class FilterFormBuildEvent extends Event
{
    public function __construct(
        public readonly ListSpec    $list,
        public readonly string      $formName,
        public FormBuilderInterface $formBuilder,
    ) {}
}
