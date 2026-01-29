<?php

namespace HeimrichHannot\FlareBundle\Projector\Registry;

use HeimrichHannot\FlareBundle\Context\ContextConfigInterface;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Projector\ProjectorInterface;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

readonly class ProjectorRegistry
{
    /**
     * @param iterable<ProjectorInterface> $projectors
     */
    public function __construct(
        #[TaggedIterator('flare.projector')]
        private iterable $projectors,
    ) {}

    /**
     * Finds the matching projector for the given config.
     *
     * @throws FlareException If no projector is found.
     */
    public function getProjectorFor(ListSpecification $spec, ContextConfigInterface $config): ProjectorInterface
    {
        $winner = null;
        $highestPriority = \PHP_INT_MIN;

        foreach ($this->projectors as $projector)
        {
            if (!$projector->supports($config)) {
                continue;
            }

            $priority = $projector->priority($spec, $config);

            if ($priority > $highestPriority) {
                $highestPriority = $priority;
                $winner = $projector;
            }
        }

        if (!$winner) {
            throw new FlareException(\sprintf(
                'No projector found supporting context configuration "%s".',
                \get_class($config)
            ));
        }

        return $winner;
    }
}