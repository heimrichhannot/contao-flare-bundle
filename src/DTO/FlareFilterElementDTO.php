<?php

namespace HeimrichHannot\FlareBundle\DTO;

use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Controller\FilterElement\AbstractFilterElementController;

readonly class FlareFilterElementDTO
{
    public function __construct(
        private string               $alias,
        private string               $class,
        private AsFilterElement $attribute,
        private object               $service
    ) {}

    public function getAlias(): string
    {
        return $this->alias;
    }

    /**
     * @return class-string<AbstractFilterElementController>
     */
    public function getClass(): string
    {
        return $this->class;
    }

    public function getAttribute(): AsFilterElement
    {
        return $this->attribute;
    }

    public function getService(): object
    {
        return $this->service;
    }
}