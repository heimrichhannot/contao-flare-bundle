<?php

namespace HeimrichHannot\FlareBundle\Filter\Element;

use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Form\Type\PublishedFilterType;

#[AsFilterElement(PublishedElement::TYPE, PublishedFilterType::class)]
#[AsFilterElement('flare_test')]
class PublishedElement extends AbstractFilterElement
{
    const TYPE = 'flare_published';
}