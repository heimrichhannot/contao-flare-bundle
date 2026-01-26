<?php

namespace HeimrichHannot\FlareBundle\Projector;

use HeimrichHannot\FlareBundle\List\ListContext;
use HeimrichHannot\FlareBundle\List\ListDefinition;
use HeimrichHannot\FlareBundle\Projector\Projection\ProjectionInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('flare.projector')]
interface ProjectorInterface
{
    /**
     * Gets the context as a string.
     *
     * @return string The context string.
     */
    public static function getContext(): string;

    /**
     * Projects a list context and definition into a projection interface.
     *
     * Throws exceptions if the projection fails.
     *
     * @param ListContext $context The list-context to be projected.
     * @param ListDefinition $config The configuration for the projection.
     *
     * @return ProjectionInterface The resulting projection interface.
     */
    public function project(ListContext $context, ListDefinition $config): ProjectionInterface;
}