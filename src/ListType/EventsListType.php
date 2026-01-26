<?php

namespace HeimrichHannot\FlareBundle\ListType;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\String\HtmlDecoder;
use HeimrichHannot\FlareBundle\Contract\Config\ListItemProviderConfig;
use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\Config\ReaderPageMetaConfig;
use HeimrichHannot\FlareBundle\Contract\ListType\ListItemProviderContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListCallback;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListType;
use HeimrichHannot\FlareBundle\Dto\ReaderPageMetaDto;
use HeimrichHannot\FlareBundle\Event\ListQueryPrepareEvent;
use HeimrichHannot\FlareBundle\FilterElement\PublishedElement;
use HeimrichHannot\FlareBundle\List\PresetFiltersConfig;
use HeimrichHannot\FlareBundle\ListItemProvider\ListItemProviderInterface;
use HeimrichHannot\FlareBundle\ListItemProvider\EventsListItemProvider;
use HeimrichHannot\FlareBundle\Util\Str;

#[AsListType(type: self::TYPE, dataContainer: 'tl_calendar_events')]
class EventsListType extends AbstractListType implements ListItemProviderContract
{
    public const TYPE = 'flare_events';

    public function __construct(
        private readonly EventsListItemProvider $itemProvider,
        private readonly HtmlDecoder            $htmlDecoder,
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

    public function onListQueryPrepareEvent(ListQueryPrepareEvent $event): void
    {
        $aliasArchive = 'events_archive';

        $builder = $event->getListQueryBuilder();

        $builder->innerJoin(
            table: 'tl_calendar',
            as: $aliasArchive,
            on: $builder->makeJoinOn($aliasArchive, joinColumn: 'id', relatedColumn: 'pid')
        );
    }

    #[AsListCallback(self::TYPE, 'preset_filters')]
    public function getPresetFilters(PresetFiltersConfig $config): void
    {
        $config->add(PublishedElement::define(), true);
    }

    public function getListItemProvider(ListItemProviderConfig $config): ?ListItemProviderInterface
    {
        return $this->itemProvider;
    }

    public function getReaderPageMeta(ReaderPageMetaConfig $config): ?ReaderPageMetaDto
    {
        global $objPage;

        /** @var CalendarEventsModel $model */
        $model = $config->getModel();

        $pageMeta = new ReaderPageMetaDto();

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