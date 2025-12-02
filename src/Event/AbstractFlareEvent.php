<?php

namespace HeimrichHannot\FlareBundle\Event;

abstract class AbstractFlareEvent
{
    abstract public function getEventName(): string;
}