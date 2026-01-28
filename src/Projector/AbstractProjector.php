<?php

namespace HeimrichHannot\FlareBundle\Projector;

use HeimrichHannot\FlareBundle\Context\ContextConfigInterface;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\View\ViewInterface;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

abstract class AbstractProjector implements ProjectorInterface
{
    /**
     * Checks if the projector supports the given list context.
     * May be used to overhaul projector logic in a future version. Until then, this method is final.
     */
    abstract public function supports(ContextConfigInterface $config): bool;

    /**
     * {@inheritdoc}
     *
     * @throws FlareException Thrown if the projector does not support the provided list context and configuration.
     */
    abstract public function project(ListSpecification $spec, ContextConfigInterface $config): ViewInterface;
}