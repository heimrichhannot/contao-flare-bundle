<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use Symfony\Contracts\EventDispatcher\Event;

class FilterElementBuildingEvent extends Event
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private readonly FilterContext          $context,
        private readonly FilterBuilderInterface $builder,
        private readonly array                  $data = [],
        private bool                            $shouldBuild = true,
    ) {}

    public function getContext(): FilterContext
    {
        return $this->context;
    }

    public function getBuilder(): FilterBuilderInterface
    {
        return $this->builder;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
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
