<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Registry;

use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\Projector\ProjectorInterface;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\List\ListSpec;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

readonly class ProjectorRegistry
{
    /**
     * @param iterable<ProjectorInterface> $projectors
     */
    public function __construct(
        #[TaggedIterator(ProjectorInterface::FLARE_PROJECTOR_TAG)]
        private iterable $projectors,
    ) {}

    /**
     * Finds the matching projector for the given config.
     *
     * @throws FlareException If no projector is found.
     */
    public function getProjectorFor(
        ListSpec         $list,
        ContextInterface $config,
        ?array           $exclude = null
    ): ProjectorInterface {
        $exclude = $exclude ? \array_fill_keys($exclude, true) : null;
        $winner = null;
        $highestPriority = \PHP_INT_MIN;

        foreach ($this->projectors as $projector)
        {
            if ($exclude && ($exclude[\get_class($projector)] ?? false)) {
                continue;
            }

            if (!$projector->supports($list, $config)) {
                continue;
            }

            $priority = $projector->priority($list, $config);

            if ($priority > $highestPriority) {
                $highestPriority = $priority;
                $winner = $projector;
            }
        }

        if (!$winner) {
            throw new FlareException(\sprintf(
                'No projector found supporting context configuration "%s".',
                \get_class($config)
            ), method: __METHOD__, source: $list->source ?? 'list inlined');
        }

        return $winner;
    }
}
