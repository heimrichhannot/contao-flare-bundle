<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Integration\ContaoCalendar\ListDriver;

use HeimrichHannot\FlareBundle\Contract\ListDriver\BuildListContract;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListDriver;
use HeimrichHannot\FlareBundle\Filter\Element\PublishedFilterElement;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterFactory;
use HeimrichHannot\FlareBundle\List\ListSpecBuilder;
use HeimrichHannot\FlareBundle\List\Driver\AbstractListDriver;
use HeimrichHannot\FlareBundle\Query\JoinTypeEnum;
use HeimrichHannot\FlareBundle\Query\SqlJoinStruct;
use HeimrichHannot\FlareBundle\Query\TableAliasRegistry;

#[AsListDriver(type: self::TYPE, dataContainer: self::DATA_CONTAINER)]
class EventsListDriver extends AbstractListDriver implements BuildListContract
{
    public const TYPE = 'flare_events';
    public const DATA_CONTAINER = 'tl_calendar_events';
    public const ALIAS_ARCHIVE = 'events_archive';

    public function __construct(
        private readonly FilterFactory $filterFactory,
    ) {}

    public function buildDca(DcaBuilder $dca, DcaContext $context): void
    {
        $dca->suffix(static function (string $suffix): string {
            if (!$suffix) {
                return $suffix;
            }

            $suffix = (string) \str_replace('sortSettings', '', $suffix);
            $suffix = \preg_replace('/(?:^|;)\{[^}]*},*(?:;|$)/', ';', $suffix);
            $suffix = \preg_replace('/;{2,}/', ';', $suffix);

            return \trim($suffix, ';');
        });
    }

    public function buildTableRegistry(TableAliasRegistry $registry): void
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

    public function buildList(ListSpecBuilder $builder): void
    {
        if ($builder->hasFilterOfType(PublishedFilterElement::TYPE)) {
            return;
        }

        $builder->addFilter($this->filterFactory->create(
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
