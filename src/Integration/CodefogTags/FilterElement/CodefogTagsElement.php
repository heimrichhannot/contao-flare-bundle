<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Integration\CodefogTags\FilterElement;

use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\FilterElement\AbstractFilterElement;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

#[AsFilterElement(
    type: self::TYPE,
    palette: '{filter_legend},fieldGeneric,isMultiple,preselect',
    formType: ChoiceType::class,
)]
class CodefogTagsElement extends AbstractFilterElement
{
    public const TYPE = 'cfg_tags';

    public function isSupported(): bool
    {
        return false;
        // todo(@ericges): implement CodefogTagsElement logic before re-enabling
        // return \class_exists(CodefogTagsBundle::class);
    }
}