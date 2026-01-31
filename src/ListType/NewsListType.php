<?php

namespace HeimrichHannot\FlareBundle\ListType;

use Contao\CoreBundle\String\HtmlDecoder;
use Contao\News;
use Contao\NewsModel;
use HeimrichHannot\FlareBundle\Contract\Config\ReaderPageMetaConfig;
use HeimrichHannot\FlareBundle\Contract\Config\ReaderPageSchemaOrgConfig;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListType;
use HeimrichHannot\FlareBundle\Dto\ReaderPageMetaDto;
use HeimrichHannot\FlareBundle\Event\ListQueryPrepareEvent;
use HeimrichHannot\FlareBundle\Event\ListSpecificationCreatedEvent;
use HeimrichHannot\FlareBundle\FilterElement\PublishedElement;
use HeimrichHannot\FlareBundle\Util\Str;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsListType(type: self::TYPE, dataContainer: 'tl_news', palette: '{filter_legend},')]
class NewsListType extends AbstractListType
{
    public const TYPE = 'flare_news';
    public const ALIAS_ARCHIVE = 'news_archive';

    public function __construct(
        private readonly HtmlDecoder $htmlDecoder,
    ) {}

    public function onListQueryPrepareEvent(ListQueryPrepareEvent $event): void
    {
        $builder = $event->getListQueryBuilder();

        $builder->innerJoin(
            table: 'tl_news_archive',
            as: self::ALIAS_ARCHIVE,
            on: $builder->makeJoinOn(self::ALIAS_ARCHIVE, 'id', 'pid')
        );
    }

    #[AsEventListener(priority: 200)]
    public function onListSpecificationCreated(ListSpecificationCreatedEvent $config): void
    {
        if ($config->listSpecification->type !== self::TYPE) {
            return;
        }

        $filters = $config->listSpecification->getFilters();

        if (!$filters->hasType(PublishedElement::TYPE)) {
            $filters->add(PublishedElement::define());
        }
    }

    public function getReaderPageMeta(ReaderPageMetaConfig $config): ?ReaderPageMetaDto
    {
        global $objPage;

        /** @var NewsModel $model */
        $model = $config->getDisplayModel();
        $contentModel = $config->getContentModel();

        $pageMeta = new ReaderPageMetaDto();

        $headline = Str::formatHeadline($model->headline) ?: Str::formatHeadline($contentModel->headline);
        $title = $headline ?: $this->htmlDecoder->inputEncodedToPlainText($objPage->title);
        $pageMeta->setTitle($title);

        $teaser = $model->teaser
            ? $this->htmlDecoder->inputEncodedToPlainText($model->teaser)
            : null;

        if ($teaser) {
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