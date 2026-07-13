<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\ListType;

use HeimrichHannot\FlareBundle\Contract\DcaContract;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListType;
use HeimrichHannot\FlareBundle\Event\ListSpecificationCreatedEvent;
use HeimrichHannot\FlareBundle\Filter\Element\PublishedFilterElement;
use HeimrichHannot\FlareBundle\Query\JoinTypeEnum;
use HeimrichHannot\FlareBundle\Query\SqlJoinStruct;
use HeimrichHannot\FlareBundle\Query\TableAliasRegistry;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsListType(type: self::TYPE, dataContainer: 'tl_news')]
class NewsListType extends AbstractListType implements DcaContract
{
    public const TYPE = 'flare_news';
    public const ALIAS_ARCHIVE = 'news_archive';

    public function buildDca(DcaBuilder $dca, DcaContext $context): void
    {
        $dca->palette('{filter_legend},');
    }

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

        $spec = $config->listSpecification;

        if (!$spec->hasFilterOfType(PublishedFilterElement::TYPE)) {
            $spec->addFilter(PublishedFilterElement::define());
        }
    }
}
