<?php

namespace HeimrichHannot\FlareBundle\Controller\FilterElement;

use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\FormType\PublishedFilterType;

#[AsFilterElement(PublishedElement::TYPE, PublishedFilterType::class)]
class PublishedElement
{
    const TYPE = 'flare_published';
}