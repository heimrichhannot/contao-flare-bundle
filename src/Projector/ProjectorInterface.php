<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Projector;

use HeimrichHannot\FlareBundle\Context\ContextConfigInterface;
use HeimrichHannot\FlareBundle\View\ViewInterface;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * @template TProjection of ViewInterface
 * @template TConfig of ContextConfigInterface
 */
#[AutoconfigureTag('flare.projector')]
interface ProjectorInterface
{
    /**
     * Checks if this projector supports the given context configuration.
     */
    public function supports(ContextConfigInterface $config): bool;

    /**
     * Projects a list specification into a result based on the context config.
     *
     * @param ListSpecification $spec
     * @param TConfig $config
     * @return TProjection
     */
    public function project(ListSpecification $spec, ContextConfigInterface $config): ViewInterface;
}