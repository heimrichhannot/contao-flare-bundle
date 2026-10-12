<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\List\ListSpec;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after every filter mounted onto the root form, before the form is built.
 *
 * Listeners may modify {@see $formBuilder} or replace it wholesale; a replacement that drops
 * mounted children makes {@see \HeimrichHannot\FlareBundle\Form\FormHarness::getChild()} return
 * null for the affected filters.
 *
 * Also dispatched under the name `flare.form.{formName}.build`
 * ({@see \HeimrichHannot\FlareBundle\EventListener\NamedDispatch\FormHarnessListener}).
 */
class FormHarnessBuildEvent extends Event
{
    public function __construct(
        public readonly ListSpec    $list,
        public readonly string      $formName,
        public FormBuilderInterface $formBuilder,
    ) {}
}
