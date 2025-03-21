<?php

namespace HeimrichHannot\FlareBundle\List\Type;

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\DataContainer;
use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\PaletteContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListType;

#[AsListType(EventsList::TYPE, dataContainer: 'tl_calendar_events')]
class EventsList implements PaletteContract
{
    public const TYPE = 'flare_events';

    public function getPalette(PaletteConfig $config): ?string
    {
        return '{test_legend},test;{another_legend},another_field';
    }
}