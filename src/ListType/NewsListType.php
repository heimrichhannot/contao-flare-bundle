<?php

namespace HeimrichHannot\FlareBundle\ListType;

use Contao\CoreBundle\String\HtmlDecoder;
use Contao\NewsModel;
use HeimrichHannot\FlareBundle\Contract\Config\PresetFiltersConfig;
use HeimrichHannot\FlareBundle\Contract\Config\ReaderPageMetaConfig;
use HeimrichHannot\FlareBundle\Contract\ListType\PresetFiltersContract;
use HeimrichHannot\FlareBundle\Contract\ListType\ReaderPageMetaContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListCallback;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListType;
use HeimrichHannot\FlareBundle\Dto\ReaderPageMetaDto;
use HeimrichHannot\FlareBundle\FilterElement\PublishedElement;
use HeimrichHannot\FlareBundle\List\ListQueryBuilder;
use HeimrichHannot\FlareBundle\Util\Str;

#[AsListType(
    alias: self::TYPE,
    dataContainer: 'tl_news',
    palette: '{filter_legend},',
)]
class NewsListType implements PresetFiltersContract, ReaderPageMetaContract
{
    public const TYPE = 'flare_news';

    public function __construct(
        private readonly HtmlDecoder $htmlDecoder,
    ) {}

    #[AsListCallback(self::TYPE, 'query.configure')]
    public function prepareQuery(ListQueryBuilder $builder): void
    {
        $aliasArchive = 'news_archive';

        $builder->innerJoin(
            table: 'tl_news_archive',
            as: 'news_archive',
            on: $builder->expr()->eq(
                $builder->column('pid'),
                $builder->column('id', of: $aliasArchive)
            )
        );


        // todo: categories ---> move to optional event listener

        /*$builder->select('*', of: 'news_archive');
        $builder->addRawSelect(<<<'SQL'
            GROUP_CONCAT(
                COALESCE(news_category.frontendTitle, news_category.title)
                ORDER BY COALESCE(news_category.frontendTitle, news_category.title)
                SEPARATOR ', '
            ) AS news_categories
        SQL);
        $builder->groupBy('id', of: 'news_archive');

        $builder->leftJoin(
            table: 'tl_news_categories',
            as: 'news_categories_joined',
            on: $builder->expr()->eq(
                $builder->column('id'),
                $builder->column('news_id', of: 'news_categories_joined')
            )
        );

        $builder->leftJoin(
            table: 'tl_news_category',
            as: 'news_category',
            on: $builder->expr()->eq(
                $builder->column('category_id', of: 'news_categories_joined'),
                $builder->column('id', of: 'new_category')
            )
        );*/
    }

    public function getPresetFilters(PresetFiltersConfig $config): void
    {
        $config->add(PublishedElement::define(), true);
    }

    public function getReaderPageMeta(ReaderPageMetaConfig $config): ?ReaderPageMetaDto
    {
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