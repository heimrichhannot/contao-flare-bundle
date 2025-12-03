<?php

namespace HeimrichHannot\FlareBundle\Event;

interface FlareDynamicEventInterface
{
    public function getEventName(): string;
}