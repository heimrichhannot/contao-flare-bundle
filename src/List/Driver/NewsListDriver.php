<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List\Driver;

use HeimrichHannot\FlareBundle\Contract\ListDriver\BuildListContract;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListDriver;
use HeimrichHannot\FlareBundle\Filter\Element\PublishedFilterElement;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterFactory;
use HeimrichHannot\FlareBundle\List\ListSpecBuilder;
use HeimrichHannot\FlareBundle\Query\JoinTypeEnum;
use HeimrichHannot\FlareBundle\Query\SqlJoinStruct;
use HeimrichHannot\FlareBundle\Query\TableAliasRegistry;

#[AsListDriver(type: self::TYPE, dataContainer: 'tl_news')]
class NewsListDriver extends AbstractListDriver implements BuildListContract
{
    public const TYPE = 'flare_news';
    public const ALIAS_ARCHIVE = 'news_archive';

    public function __construct(
        private readonly FilterFactory $filterFactory,
    ) {}

    public function buildDca(DcaBuilder $dca, DcaContext $context): void
    {
        $dca->palette('{filter_legend},');
    }

    public function buildTableRegistry(TableAliasRegistry $registry): void
    {
        $registry->registerJoin(new SqlJoinStruct(
            fromAlias: TableAliasRegistry::ALIAS_MAIN,
            joinType: JoinTypeEnum::INNER,
            table: 'tl_news_archive',
            joinAlias: self::ALIAS_ARCHIVE,
            condition: $registry->makeJoinOn(self::ALIAS_ARCHIVE, 'id', TableAliasRegistry::ALIAS_MAIN, 'pid')
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
