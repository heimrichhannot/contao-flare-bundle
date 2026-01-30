<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Projector;

use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\View\ViewInterface;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * @template TProjection of ViewInterface
 * @template TConfig of ContextInterface
 */
#[AutoconfigureTag('flare.projector')]
interface ProjectorInterface
{
    /**
     * Checks if this projector supports the given context configuration.
     */
    public function supports(ContextInterface $config): bool;

    /**
     * Calculates the priority of the projector when supported, considering the given specification.
     */
    public function priority(ListSpecification $spec, ContextInterface $config): int;

    /**
     * Projects a list specification into a result based on the context config.
     *
     * @param ListSpecification $spec
     * @param ContextInterface $config
     * @return ViewInterface
     */
    public function project(ListSpecification $spec, ContextInterface $config): ViewInterface;
}