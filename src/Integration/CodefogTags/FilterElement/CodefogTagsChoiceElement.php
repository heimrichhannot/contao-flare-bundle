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
    isTargeted: true,
)]
class CodefogTagsChoiceElement extends AbstractFilterElement
{
    public const TYPE = 'cfg_tags_choice';
}