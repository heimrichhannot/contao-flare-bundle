<?php

namespace HeimrichHannot\FlareBundle\List\Type;

use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListType;

#[AsListType(
    alias: NewsListType::TYPE,
    dataContainer: 'tl_news'
)]
class NewsListType extends AbstractListType
{
    public const TYPE = 'flare_news';
}