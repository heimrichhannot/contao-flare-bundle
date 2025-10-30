<?php

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\ListView\Resolver\ListViewResolverInterface;
use Symfony\Contracts\EventDispatcher\Event;

class CreateListViewBuilderEvent extends Event
{
    private ?ListViewResolverInterface $resolver;

    public function setResolver(?ListViewResolverInterface $resolver): void
    {
        $this->resolver = $resolver;
    }

    public function getResolver(ListViewResolverInterface $fallback): ListViewResolverInterface
    {
        return $this->resolver ?? $fallback;
    }
}