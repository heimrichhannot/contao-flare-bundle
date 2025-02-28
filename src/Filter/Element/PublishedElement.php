<?php

namespace HeimrichHannot\FlareBundle\Filter\Element;

use Contao\DataContainer;
use HeimrichHannot\FlareBundle\Contao\TypePaletteInterface;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;

#[AsFilterElement(PublishedElement::TYPE)]
class PublishedElement extends AbstractFilterElement implements TypePaletteInterface
{
    const TYPE = 'flare_published';

    public function getPalette(string $alias, DataContainer $dc): string
    {
        return '';
    }
}