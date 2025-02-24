<?php

namespace HeimrichHannot\FlareBundle\FilterElement;

use HeimrichHannot\FlareBundle\Attribute\AsFlareFilterElement;

#[AsFlareFilterElement(PublishedFilterElement::TYPE)]
class PublishedFilterElement
{
    const TYPE = 'flare_published';
}