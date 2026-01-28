<?php

namespace HeimrichHannot\FlareBundle\Projector\Registry;

use HeimrichHannot\FlareBundle\Context\ContextConfigInterface;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Projector\ProjectorInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

readonly class ProjectorRegistry
{
    public function __construct(
        #[TaggedIterator('flare.projector')]
        private iterable $projectors,
    ) {}

    /**
     * Finds the matching projector for the given config.
     *
     * @throws FlareException If no projector is found.
     */
    public function getProjectorFor(ContextConfigInterface $config): ProjectorInterface
    {
        foreach ($this->projectors as $projector) {
            if ($projector->supports($config)) {
                return $projector;
            }
        }

        throw new FlareException(\sprintf(
            'No projector found supporting context configuration "%s".',
            \get_class($config)
        ));
    }
}