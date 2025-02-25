<?php

namespace HeimrichHannot\FlareBundle\FilterElement;

use HeimrichHannot\FlareBundle\Attribute\AsFlareFilterElement;

#[AsFlareFilterElement(PublishedElement::TYPE)]
class PublishedElement
{
    const TYPE = 'flare_published';
}