<?php

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\ListView\Resolver\ListViewResolverInterface;
use Symfony\Contracts\EventDispatcher\Event;

class CreateListViewBuilderEvent extends Event
{
    private ?ListViewResolverInterface $resolver = null;

    public function __construct(
        private readonly ListViewResolverInterface $defaultResolver,
    ) {}

    public function setResolver(?ListViewResolverInterface $resolver): void
    {
        $this->resolver = $resolver;
    }

    public function getResolver(): ?ListViewResolverInterface
    {
        return $this->resolver ?? $this->defaultResolver;
    }
}