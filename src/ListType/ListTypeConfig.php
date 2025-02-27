<?php

namespace HeimrichHannot\FlareBundle\ListType;

use HeimrichHannot\FlareBundle\DependencyInjection\Registry\ConfigInterface;

class ListTypeConfig implements ConfigInterface
{
    public const TAG = 'huh.flare.list_type';

    /**
     * @see \Symfony\Component\HttpKernel\Fragment\FragmentHandler::render()
     */
    public function __construct(
        private $controller,
        private array $attributes = [],
    ) {}

    public function getController()
    {
        return $this->controller;
    }

    public function setController($controller): void
    {
        $this->controller = $controller;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function setAttributes(array $attributes): void
    {
        $this->attributes = $attributes;
    }
}