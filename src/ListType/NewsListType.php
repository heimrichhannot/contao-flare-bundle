<?php

namespace HeimrichHannot\FlareBundle\ListType;

use Contao\CoreBundle\String\HtmlDecoder;
use Contao\NewsModel;
use HeimrichHannot\FlareBundle\Contract\Config\PresetFiltersConfig;
use HeimrichHannot\FlareBundle\Contract\Config\ReaderPageMetaConfig;
use HeimrichHannot\FlareBundle\Contract\ListType\PresetFiltersContract;
use HeimrichHannot\FlareBundle\Contract\ListType\ReaderPageMetaContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListType;
use HeimrichHannot\FlareBundle\Dto\ReaderPageMetaDto;
use HeimrichHannot\FlareBundle\FilterElement\PublishedElement;
use HeimrichHannot\FlareBundle\Util\Str;

#[AsListType(
    alias: NewsListType::TYPE,
    dataContainer: 'tl_news',
    palette: '{filter_legend},',
)]
class NewsListType implements PresetFiltersContract, ReaderPageMetaContract
{
    public const TYPE = 'flare_news';

    public function __construct(
        private HtmlDecoder $htmlDecoder,
    ) {}

    public function getPresetFilters(PresetFiltersConfig $config): void
    {
        // $listModel = $config->getListModel();

        $config->add(PublishedElement::define(), true);
    }

    public function getReaderPageMeta(ReaderPageMetaConfig $config): ?ReaderPageMetaDto {
        global $objPage;

        /** @var NewsModel $model */
        $model = $config->getModel();
        $contentModel = $config->getContentModel();

        $pageMeta = new ReaderPageMetaDto();

        $pageMeta->setTitle(Str::getHeadline(
            $model->headline
                ? $this->htmlDecoder->inputEncodedToPlainText($model->headline)
                : $contentModel->headline
        ) ?: $this->htmlDecoder->inputEncodedToPlainText($objPage->title));

        if ($teaser = $model->teaser ? $this->htmlDecoder->inputEncodedToPlainText($model->teaser) : null) {
            $pageMeta->setDescription(Str::htmlToMeta($teaser, 250));
        }

        return $pageMeta;
    }
}