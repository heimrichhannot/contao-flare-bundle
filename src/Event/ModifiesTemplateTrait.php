<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use Contao\Template;

trait ModifiesTemplateTrait
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