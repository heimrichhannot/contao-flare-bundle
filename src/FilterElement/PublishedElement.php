<?php

namespace HeimrichHannot\FlareBundle\FilterElement;

use HeimrichHannot\FlareBundle\Attribute\AsFlareFilterElement;
use HeimrichHannot\FlareBundle\FormType\PublishedFilterType;

#[AsFlareFilterElement(PublishedElement::TYPE, PublishedFilterType::class)]
class PublishedElement
{
    const TYPE = 'flare_published';
}