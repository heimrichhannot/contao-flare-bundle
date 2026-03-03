<?php

namespace HeimrichHannot\FlareBundle\Engine;

use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\View\ViewInterface;
use HeimrichHannot\FlareBundle\Registry\ProjectorRegistry;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

final class Engine
{
    public function __construct(
        private ContextInterface  $context,
        private ListSpecification $listSpecification,
        private readonly ProjectorRegistry $projectorRegistry,
    ) {}

    public function getContext(): ContextInterface
    {
        return $this->context;
    }

    public function getSpecification(): ListSpecification
    {
        return $this->listSpecification;
    }

    public function createView(): ViewInterface
    {
        return $this->projectorRegistry
            ->getProjectorFor($this->listSpecification, $this->context)
            ->project($this->listSpecification, $this->context);
    }

    public function clone(): self
    {
        return clone $this;
    }

    public function __clone(): void
    {
        $this->context = clone $this->context;
        $this->listSpecification = clone $this->listSpecification;
    }
}