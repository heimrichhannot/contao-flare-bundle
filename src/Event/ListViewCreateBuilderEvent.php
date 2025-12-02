<?php

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\ListView\Resolver\ListViewResolverInterface;

class ListViewCreateBuilderEvent extends AbstractFlareEvent
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

    public function getEventName(): string
    {
        return 'flare.list_view.create_builder';
    }
}