<?php

namespace HeimrichHannot\FlareBundle\Event;

use Contao\Template;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractTemplateRenderEvent extends Event
{
    abstract public function getTemplate(): Template;

    public function set(string $name, mixed $value): void
    {
        $this->getTemplate()->{$name} = $value;
    }

    public function get(string $name): mixed
    {
        return $this->getTemplate()->{$name};
    }
}