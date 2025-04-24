<?php

namespace HeimrichHannot\FlareBundle\List\Type;

use HeimrichHannot\FlareBundle\Contract\Config\FilterDefinition;
use HeimrichHannot\FlareBundle\Contract\Config\PresetFiltersConfig;
use HeimrichHannot\FlareBundle\Contract\ListType\PresetFiltersContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListType;
use HeimrichHannot\FlareBundle\Filter\Element\PublishedElement;

#[AsListType(
    alias: NewsListType::TYPE,
    dataContainer: 'tl_news',
    palette: '{filter_legend},',
)]
class NewsListType implements PresetFiltersContract
{
    public const TYPE = 'flare_news';

    public function getPresetFilters(PresetFiltersConfig $config): void
    {
        $listModel = $config->getListModel();

        $published = new FilterDefinition(
            type: PublishedElement::TYPE,
            title: 'Is Published',
            intrinsic: true,
        );

        $published->usePublished = true;
        $published->fieldPublished = 'published';

        $published->useStart = true;
        $published->fieldStart = 'start';

        $published->useStop = true;
        $published->fieldStop = 'stop';

        $config->add($published, true);
    }
}