<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\EventListener\NamedDispatch;

use HeimrichHannot\FlareBundle\Event\FormHarnessBuildEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class FormHarnessListener
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    #[AsEventListener(priority: -200)]
    public function onFormHarnessBuildEvent(FormHarnessBuildEvent $event): void
    {
        $eventName = "flare.form.{$event->formName}.build";

        $this->eventDispatcher->dispatch(event: $event, eventName: $eventName);
    }
}
