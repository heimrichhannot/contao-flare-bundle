<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Projector;

use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\View\ViewInterface;
use HeimrichHannot\FlareBundle\List\ListSpec;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * @template TView of ViewInterface
 * @template TContext of ContextInterface
 */
#[AutoconfigureTag(self::FLARE_PROJECTOR_TAG)]
interface ProjectorInterface
{
    public const FLARE_PROJECTOR_TAG = 'flare.projector';

    /**
     * Checks if this projector supports the given context configuration.
     */
    public function supports(ListSpec $list, ContextInterface $context): bool;

    /**
     * Calculates the priority of the projector when supported, considering the given specification.
     */
    public function priority(ListSpec $list, ContextInterface $context): int;

    /**
     * Projects a list specification into a result based on the context config.
     *
     * @param ListSpec $list
     * @param ContextInterface  $context
     * @return ViewInterface
     */
    public function project(ListSpec $list, ContextInterface $context): ViewInterface;
}
