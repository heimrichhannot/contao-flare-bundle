<?php

namespace HeimrichHannot\FlareBundle\FilterElement;

use Codefog\TagsBundle\CodefogTagsBundle;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

#[AsFilterElement(
    alias: CodefogTagsElement::TYPE,
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