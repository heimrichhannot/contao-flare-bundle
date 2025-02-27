<?php

namespace HeimrichHannot\FlareBundle\List\Type;

use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListType;

#[AsListType(NewsListType::TYPE)]
class NewsListType extends AbstractListType
{
    public const TYPE = 'flare_news';
}