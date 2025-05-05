<?php

namespace HeimrichHannot\FlareBundle\List\Type;

use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\Config\PresetFiltersConfig;
use HeimrichHannot\FlareBundle\Contract\ListType\PresetFiltersContract;
use HeimrichHannot\FlareBundle\Contract\PaletteContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListType;
use HeimrichHannot\FlareBundle\Filter\Element\PublishedElement;

#[AsListType(EventsList::TYPE, dataContainer: 'tl_calendar_events')]
class EventsList implements PaletteContract, PresetFiltersContract
{
    public const TYPE = 'flare_events';

    public function getPalette(PaletteConfig $config): ?string
    {
        return null;
    }

    public function getPresetFilters(PresetFiltersConfig $config): void
    {
        $config->add(PublishedElement::define(), true);
    }
}