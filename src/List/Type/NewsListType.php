<?php

namespace HeimrichHannot\FlareBundle\List\Type;

use Contao\DataContainer;
use HeimrichHannot\FlareBundle\Contao\TypeSubpaletteInterface;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListType;

/**
 * @method string getLocale()
 */
#[AsListType(NewsListType::TYPE)]
class NewsListType extends AbstractListType implements TypeSubpaletteInterface
{
    public const TYPE = 'flare_news';

    public function getSubpalette(string $alias, DataContainer $dc): string
    {
        return '{publish_legend},published,published';
    }
}