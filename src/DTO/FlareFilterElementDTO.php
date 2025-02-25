<?php

namespace HeimrichHannot\FlareBundle\DTO;

use HeimrichHannot\FlareBundle\Attribute\AsFlareFilterElement;
use HeimrichHannot\FlareBundle\FilterElement\AbstractFilterElement;

readonly class FlareFilterElementDTO
{
    public function __construct(
        private string               $alias,
        private string               $class,
        private AsFlareFilterElement $attribute,
        private object               $service
    ) {}

    public function getAlias(): string
    {
        return $this->alias;
    }

    /**
     * @return class-string<AbstractFilterElement>
     */
    public function getClass(): string
    {
        return $this->class;
    }

    public function getAttribute(): AsFlareFilterElement
    {
        return $this->attribute;
    }

    public function getService(): object
    {
        return $this->service;
    }
}