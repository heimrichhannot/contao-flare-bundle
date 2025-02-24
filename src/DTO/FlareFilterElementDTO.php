<?php

namespace HeimrichHannot\FlareBundle\DTO;

use HeimrichHannot\FlareBundle\Attribute\AsFlareFilterElement;

readonly class FlareFilterElementDTO
{
    public function __construct(
        private string               $alias,
        private string               $class,
        private AsFlareFilterElement $attribute
    ) {}

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function getAttribute(): AsFlareFilterElement
    {
        return $this->attribute;
    }
}