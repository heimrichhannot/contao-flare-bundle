<?php

namespace HeimrichHannot\FlareBundle\Filter\Element;

use Contao\DataContainer;
use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\PaletteContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;

#[AsFilterElement(PublishedElement::TYPE)]
class PublishedElement extends AbstractFilterElement implements PaletteContract
{
    const TYPE = 'flare_published';

    public function getPalette(PaletteConfig $config): ?string
    {
        return '';
    }
}