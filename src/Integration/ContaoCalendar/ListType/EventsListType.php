<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Integration\ContaoCalendar\ListType;

use HeimrichHannot\FlareBundle\Contract\DcaContract;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListType;
use HeimrichHannot\FlareBundle\Event\ListSpecificationCreatedEvent;
use HeimrichHannot\FlareBundle\Filter\Element\PublishedFilterElement;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\ListType\AbstractListType;
use HeimrichHannot\FlareBundle\Query\JoinTypeEnum;
use HeimrichHannot\FlareBundle\Query\SqlJoinStruct;
use HeimrichHannot\FlareBundle\Query\TableAliasRegistry;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsListType(type: self::TYPE, dataContainer: self::DATA_CONTAINER)]
class EventsListType extends AbstractListType implements DcaContract
{
    public const TYPE = 'flare_events';
    public const DATA_CONTAINER = 'tl_calendar_events';
    public const ALIAS_ARCHIVE = 'events_archive';

    public function buildDca(DcaBuilder $dca, DcaContext $context): void
    {
        $dca->suffix(static function (string $suffix): string {
            if (!$suffix) {
                return $suffix;
            }

            $suffix = \str_replace('sortSettings', '', $suffix);
            $suffix = \preg_replace('/(?:^|;)\{[^}]*},*(?:;|$)/', ';', $suffix);
            $suffix = \preg_replace('/;{2,}/', ';', $suffix);

            return \trim($suffix, ';');
        });
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

        $spec = $config->listSpecification;

        if (!$spec->hasFilterOfType(PublishedFilterElement::TYPE)) {
            $spec->addFilter(new Filter(
                element: PublishedFilterElement::TYPE,
                config: [
                    'intrinsic' => true,
                    'published_field' => 'published',
                    'start_field' => 'start',
                    'stop_field' => 'stop',
                    'invert' => false,
                ],
            ));
        }
    }
}
