<?php

namespace HeimrichHannot\FlareBundle\List\Type;

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\DataContainer;
use HeimrichHannot\FlareBundle\Contao\TypePaletteInterface;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListType;

/**
 * @method string getLocale()
 */
#[AsListType(NewsListType::TYPE)]
class NewsListType extends AbstractListType implements TypePaletteInterface
{
    public const TYPE = 'flare_news';

    public function getPalette(string $alias, DataContainer $dc): PaletteManipulator|string|null
    {
        return PaletteManipulator::create()
            ->addLegend('subpalette_legend')
            ->addField('test', 'subpalette_legend', PaletteManipulator::POSITION_APPEND)
            ->addLegend('test_legend')
            ->addField('another_field', 'test_legend', PaletteManipulator::POSITION_APPEND)
        ;
        // return ';{test_legend},test;{another_legend},another_field;';
    }
}