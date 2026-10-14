<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterInvocation;
use Symfony\Contracts\EventDispatcher\Event;

class FilterElementBuildingEvent extends Event
{
    public function __construct(
        private readonly FilterInvocation      $invocation,
        private readonly ContextInterface      $context,
        private readonly FilterBuilderInterface $builder,
        private bool                           $shouldBuild,
    ) {}

    public function getInvocation(): FilterInvocation
    {
        return $this->invocation;
    }

    public function getContext(): ContextInterface
    {
        return $this->context;
    }

    public function getBuilder(): FilterBuilderInterface
    {
        return $this->builder;
    }

    public function shouldBuild(): bool
    {
        return $this->shouldBuild;
    }

    public function setShouldBuild(bool $shouldBuild): void
    {
        $this->shouldBuild = $shouldBuild;
    }
}