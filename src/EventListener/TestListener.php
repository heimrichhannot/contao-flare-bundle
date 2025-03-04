<?php

namespace HeimrichHannot\FlareBundle\EventListener;

use HeimrichHannot\FlareBundle\Event\FilterElementInvokedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

class TestListener
{
    #[AsEventListener('huh.flare.filter_element.flare_published.invoked')]
    public function onFilterElementInvoked(FilterElementInvokedEvent $event): void
    {
        \dump($event->getQueryBuilder()->getConditions());
    }
}