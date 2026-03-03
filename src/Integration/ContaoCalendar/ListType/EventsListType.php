<?php

namespace HeimrichHannot\FlareBundle\Integration\ContaoCalendar\ListType;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\String\HtmlDecoder;
use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\Config\ReaderPageMetaConfig;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListType;
use HeimrichHannot\FlareBundle\Event\ListSpecificationCreatedEvent;
use HeimrichHannot\FlareBundle\FilterElement\PublishedElement;
use HeimrichHannot\FlareBundle\ListType\AbstractListType;
use HeimrichHannot\FlareBundle\Query\JoinTypeEnum;
use HeimrichHannot\FlareBundle\Query\SqlJoinStruct;
use HeimrichHannot\FlareBundle\Query\TableAliasRegistry;
use HeimrichHannot\FlareBundle\Reader\ReaderPageMeta;
use HeimrichHannot\FlareBundle\Util\Str;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsListType(type: self::TYPE, dataContainer: self::DATA_CONTAINER)]
class EventsListType extends AbstractListType
{
    public const TYPE = 'flare_events';
    public const DATA_CONTAINER = 'tl_calendar_events';
    public const ALIAS_ARCHIVE = 'events_archive';

    public function __construct(
        private readonly HtmlDecoder $htmlDecoder,
    ) {}

    public function getPalette(PaletteConfig $config): ?string
    {
        if ($suffix = $config->getSuffix())
        {
            $suffix = \str_replace('sortSettings', '', $suffix);
            $suffix = \preg_replace('/(?:^|;)\{[^}]*},*(?:;|$)/', ';', $suffix);
            $suffix = \preg_replace('/;{2,}/', ';', $suffix);
            $suffix = \trim($suffix, ';');
            $config->setSuffix($suffix);
        }

        return null;
    }

    public function configureTableRegistry(TableAliasRegistry $registry): void
    {
        $fromAlias = TableAliasRegistry::ALIAS_MAIN;

        $registry->registerJoin(new SqlJoinStruct(
            fromAlias: $fromAlias,
            joinType: JoinTypeEnum::INNER,
            table: 'tl_calendar',
            joinAlias: self::ALIAS_ARCHIVE,
            condition: $registry->makeJoinOn(self::ALIAS_ARCHIVE, 'id', $fromAlias, 'pid')
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

        /** @var CalendarEventsModel $model */
        $model = $config->getDisplayModel();

        $pageMeta = new ReaderPageMeta();

        /** @mago-expect lint:no-nested-ternary This is a fine fallback chain. */
        $pageMeta->setTitle($this->htmlDecoder->inputEncodedToPlainText(
            $model->pageTitle ?: $model->title ?: $objPage->title
        ));

        $description = null;

        if ($model->description) {
            $description = $this->htmlDecoder->inputEncodedToPlainText($model->description) ?: null;
        }

        if (!$description && $model->teaser) {
            $description = Str::htmlToMeta($this->htmlDecoder->inputEncodedToPlainText($model->teaser), 250) ?: null;
        }

        if ($description) {
            $pageMeta->setDescription($description);
        }

        if ($robots = $model->robots ?: null) {
            $pageMeta->setRobots($robots);
        }

        return $pageMeta;
    }
}