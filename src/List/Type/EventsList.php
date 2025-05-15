<?php

namespace HeimrichHannot\FlareBundle\List\Type;

use HeimrichHannot\FlareBundle\Contract\Config\ListItemProviderConfig;
use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\Config\PresetFiltersConfig;
use HeimrichHannot\FlareBundle\Contract\ListType\ListItemProviderContract;
use HeimrichHannot\FlareBundle\Contract\ListType\PresetFiltersContract;
use HeimrichHannot\FlareBundle\Contract\PaletteContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListType;
use HeimrichHannot\FlareBundle\Filter\Element\PublishedElement;
use HeimrichHannot\FlareBundle\List\ListItemProviderInterface;
use HeimrichHannot\FlareBundle\List\Type\ItemProvider\EventsListItemProvider;

#[AsListType(EventsList::TYPE, dataContainer: 'tl_calendar_events')]
class EventsList implements ListItemProviderContract, PaletteContract, PresetFiltersContract
{
    public const TYPE = 'flare_events';

    public function __construct(
        private readonly EventsListItemProvider $itemProvider,
    ) {}

    public function getPalette(PaletteConfig $config): ?string
    {
        if ($suffix = $config->getSuffix())
        {
            $suffix = \str_replace('sortSettings', '', $suffix);
            $suffix = \preg_replace('/(?:^|;)\{[^}]*},*(?:;|$)/', ';', $suffix);
            $suffix = \preg_replace('/;{2,}/', ';', $suffix);
            $suffix = \trim($suffix, ';');
            $config->setSuffix($suffix);
        }

        return null;
    }

    public function getPresetFilters(PresetFiltersConfig $config): void
    {
        $config->add(PublishedElement::define(), true);
    }

    public function getListItemProvider(ListItemProviderConfig $config): ?ListItemProviderInterface
    {
        return $this->itemProvider;
    }
}