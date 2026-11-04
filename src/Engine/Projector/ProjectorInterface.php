<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Projector;

use HeimrichHannot\FlareBundle\Engine\Intent\IntentInterface;
use HeimrichHannot\FlareBundle\Engine\View\ViewInterface;
use HeimrichHannot\FlareBundle\List\ListCompound;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * @template TView of ViewInterface
 * @template TIntent of IntentInterface
 */
#[AutoconfigureTag(self::FLARE_PROJECTOR_TAG)]
interface ProjectorInterface
{
    public const FLARE_PROJECTOR_TAG = 'flare.projector';

    /**
     * Checks if this projector supports the given intent.
     */
    public function supports(ListCompound $list, IntentInterface $intent): bool;

    /**
     * Calculates the priority of the projector when supported, considering the given list.
     */
    public function priority(ListCompound $list, IntentInterface $intent): int;

    /**
     * Projects a list into a result based on the intent.
     *
     * @param ListCompound $list
     * @param IntentInterface  $intent
     * @return ViewInterface
     */
    public function project(ListCompound $list, IntentInterface $intent): ViewInterface;
}
