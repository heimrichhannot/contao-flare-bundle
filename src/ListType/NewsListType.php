<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\ListType;

use Contao\CoreBundle\String\HtmlDecoder;
use Contao\NewsModel;
use HeimrichHannot\FlareBundle\Contract\Config\ReaderPageMetaConfig;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListType;
use HeimrichHannot\FlareBundle\Event\ListSpecificationCreatedEvent;
use HeimrichHannot\FlareBundle\FilterElement\PublishedElement;
use HeimrichHannot\FlareBundle\Query\JoinTypeEnum;
use HeimrichHannot\FlareBundle\Query\SqlJoinStruct;
use HeimrichHannot\FlareBundle\Query\TableAliasRegistry;
use HeimrichHannot\FlareBundle\Reader\ReaderPageMeta;
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

    public function configureTableRegistry(TableAliasRegistry $registry): void
    {
        $registry->registerJoin(new SqlJoinStruct(
            fromAlias: TableAliasRegistry::ALIAS_MAIN,
            joinType: JoinTypeEnum::INNER,
            table: 'tl_news_archive',
            joinAlias: self::ALIAS_ARCHIVE,
            condition: $registry->makeJoinOn(self::ALIAS_ARCHIVE, 'id', TableAliasRegistry::ALIAS_MAIN, 'pid')
        ));
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

    public function getReaderPageMeta(ReaderPageMetaConfig $config): ?ReaderPageMeta
    {
        global $objPage;

        /** @var NewsModel $model */
        $model = $config->getDisplayModel();
        $contentModel = $config->getContentModel();

        $pageMeta = new ReaderPageMeta();

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
}