<?php

namespace HeimrichHannot\FlareBundle\DTO;

use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Filter\Element\AbstractFilterElement;

readonly class FlareFilterElementDTO
{
    /**
     * @param AsFilterElement $attribute
     * @param string $alias
     * @param class-string<\HeimrichHannot\FlareBundle\Filter\Element\AbstractFilterElement> $class
     * @param object $service
     */
    public function __construct(
        private AsFilterElement $attribute,
        private string          $alias,
        private string          $class,
        private object          $service
    ) {}

    public function getAlias(): string
    {
        return $this->alias;
    }

    /**
     * @return class-string<\HeimrichHannot\FlareBundle\Filter\Element\AbstractFilterElement>
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