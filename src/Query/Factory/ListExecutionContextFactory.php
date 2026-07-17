<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Query\Factory;

use HeimrichHannot\FlareBundle\Contract\ListType\BuildQueryContract;
use HeimrichHannot\FlareBundle\Event\QueryBaseInitializedEvent;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\Query\ListExecutionContext;
use HeimrichHannot\FlareBundle\Query\SqlQueryStruct;
use HeimrichHannot\FlareBundle\Query\TableAliasRegistry;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class ListExecutionContextFactory
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * @throws FlareException
     */
    public function create(ListSpec $list): ListExecutionContext
    {
        $driver = $list->driver;

        if (!$mainTable = $list->getDataContainerName())
        {
            throw new FlareException(
                \sprintf('Failed to evaluate data container table of list "%s".', $list->source ?? \get_class($driver)),
                method: __METHOD__,
            );
        }

        $registry = new TableAliasRegistry();
        $registry->setMainTable($mainTable);

        $struct = (new SqlQueryStruct())
            ->setFrom($mainTable)
            ->setFromAlias(TableAliasRegistry::ALIAS_MAIN)
            ->setSelect([TableAliasRegistry::ALIAS_MAIN . '.*'])
            ->setGroupBy([TableAliasRegistry::ALIAS_MAIN . '.id']);

        if ($driver instanceof BuildQueryContract) {
            $driver->buildTableRegistry($registry);
            $driver->buildBaseQuery($struct);
        }

        $this->eventDispatcher->dispatch(new QueryBaseInitializedEvent(
            list: $list,
            registry: $registry,
            struct: $struct,
        ));

        // Re-ensure id selection for internal logic
        $select = $struct->getSelect() ?? [];
        $select[] = TableAliasRegistry::ALIAS_MAIN . '.id AS id';
        $struct->setSelect(\array_unique($select));

        return new ListExecutionContext($registry, $struct);
    }
}
