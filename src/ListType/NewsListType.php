<?php

namespace HeimrichHannot\FlareBundle\ListType;

use Contao\CoreBundle\String\HtmlDecoder;
use Contao\News;
use Contao\NewsModel;
use HeimrichHannot\FlareBundle\Contract\Config\PresetFiltersConfig;
use HeimrichHannot\FlareBundle\Contract\Config\ReaderPageMetaConfig;
use HeimrichHannot\FlareBundle\Contract\Config\ReaderPageSchemaOrgConfig;
use HeimrichHannot\FlareBundle\Contract\ListType\PresetFiltersContract;
use HeimrichHannot\FlareBundle\Contract\ListType\ReaderPageSchemaOrContract;
use HeimrichHannot\FlareBundle\Contract\ListType\ReaderPageMetaContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListType;
use HeimrichHannot\FlareBundle\Dto\ReaderPageMetaDto;
use HeimrichHannot\FlareBundle\FilterElement\PublishedElement;
use HeimrichHannot\FlareBundle\Util\Str;

#[AsListType(
    alias: self::TYPE,
    dataContainer: 'tl_news',
    palette: '{filter_legend},',
)]
class NewsListType implements PresetFiltersContract, ReaderPageMetaContract, ReaderPageSchemaOrContract
{
    public const TYPE = 'flare_news';

    public function __construct(
        private readonly HtmlDecoder $htmlDecoder,
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

    public function getReaderPageSchemaOrg(ReaderPageSchemaOrgConfig $config): ?array
    {
        /** @var NewsModel $model */
        $model = $config->model;
        return News::getSchemaOrgData($model);
    }
}