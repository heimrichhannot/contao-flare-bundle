<?php

namespace HeimrichHannot\FlareBundle\Projector;

use HeimrichHannot\FlareBundle\List\ListContext;
use HeimrichHannot\FlareBundle\List\ListDefinition;
use HeimrichHannot\FlareBundle\Projector\Projection\ProjectionInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * @template T of ProjectionInterface
 * @phpstan-template T of ProjectionInterface
 * @psalm-template T of ProjectionInterface
 */
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
     * Gets the projection class for this projector.
     * @return class-string<T> The projection class.
     */
    public static function getProjectionClass(): string;

    /**
     * Projects a list context and definition into a projection interface.
     *
     * Throws exceptions if the projection fails.
     *
     * @param ListContext $listContext The list-context to be projected.
     * @param ListDefinition $listDefinition The configuration for the projection.
     *
     * @return T The resulting projection interface.
     * @phpstan-return T The resulting projection interface.
     * @psalm-return T The resulting projection interface.
     */
    public function project(ListContext $listContext, ListDefinition $listDefinition): ProjectionInterface;
}