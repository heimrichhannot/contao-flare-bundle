<?php

namespace HeimrichHannot\FlareBundle\FilterElement;

use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Form\Type\PublishedFilterType;

#[AsFilterElement(Published::ALIAS, PublishedFilterType::class)]
#[AsFilterElement('flare_test')]
class Published extends AbstractFilterElement
{
    const ALIAS = 'flare_published';
}