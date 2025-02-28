<?php

namespace HeimrichHannot\FlareBundle\List\Type;

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

    public function getPalette(string $alias, DataContainer $dc): string
    {
        return '{test_legend},test;{another_legend},another_field';
    }

    public function getBaseDataContainerName(array $row, DataContainer $dc): string
    {
        return 'tl_news';
    }
}