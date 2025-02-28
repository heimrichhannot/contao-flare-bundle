<?php

namespace HeimrichHannot\FlareBundle\List\Type;

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\DataContainer;
use HeimrichHannot\FlareBundle\Contao\TypePaletteInterface;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListType;

/**
 * @method string getLocale()
 */
#[AsListType(EventsListType::TYPE)]
class EventsListType extends AbstractListType implements TypePaletteInterface
{
    public const TYPE = 'flare_events';

    public function getPalette(string $alias, DataContainer $dc): PaletteManipulator|string|null
    {
        return '{test_legend},test;{another_legend},another_field';
    }
}