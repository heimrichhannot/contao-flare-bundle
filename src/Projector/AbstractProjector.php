<?php

namespace HeimrichHannot\FlareBundle\Projector;

use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\List\ListContext;
use HeimrichHannot\FlareBundle\List\ListDefinition;
use HeimrichHannot\FlareBundle\Projector\Projection\ProjectionInterface;

abstract class AbstractProjector implements ProjectorInterface
{
    /**
     * {@inheritdoc}
     */
    abstract public static function getContext(): string;

    /**
     * Checks if the projector supports the given list context.
     * May be used to overhaul projector logic in a future version. Until then, this method is final.
     */
    final public function supports(ListContext $context, ListDefinition $listDefinition): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @throws FlareException Thrown if the projector does not support the provided list context and configuration.
     */
    final public function project(ListContext $context, ListDefinition $config): ProjectionInterface
    {
        if (!$this->supports($context, $config))
        {
            throw new FlareException(\sprintf(
                'Projector "%s" does not support list context',
                static::class,
            ));
        }

        return $this->execute($context, $config);
    }

    /**
     * Executes the projection logic for the given list context and list definition.
     *
     * @param ListContext $context The context of the list in which the projection is executed.
     * @param ListDefinition $listDefinition The configuration of the list on which the projection is executed.
     *
     * @return ProjectionInterface The result of the executed projection.
     */
    abstract protected function execute(ListContext $context, ListDefinition $listDefinition): ProjectionInterface;
}