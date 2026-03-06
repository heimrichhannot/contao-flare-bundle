<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine;

use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\View\ViewInterface;
use HeimrichHannot\FlareBundle\Registry\ProjectorRegistry;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

final class Engine
{
    public function __construct(
        private ContextInterface           $context,
        private ListSpecification          $list,
        private readonly ProjectorRegistry $projectorRegistry,
    ) {}

    public function getContext(): ContextInterface
    {
        return $this->context;
    }

    public function getList(): ListSpecification
    {
        return $this->list;
    }

    public function createView(): ViewInterface
    {
        return $this->projectorRegistry
            ->getProjectorFor($this->list, $this->context)
            ->project($this->list, $this->context);
    }

    public function clone(): self
    {
        return clone $this;
    }

    public function __clone(): void
    {
        $this->context = clone $this->context;
        $this->list = clone $this->list;
    }
}