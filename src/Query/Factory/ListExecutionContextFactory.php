<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Query\Factory;

use HeimrichHannot\FlareBundle\Contract\ListType\BuildQueryContract;
use HeimrichHannot\FlareBundle\Event\QueryBaseInitializedEvent;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Query\ListExecutionContext;
use HeimrichHannot\FlareBundle\Query\SqlQueryStruct;
use HeimrichHannot\FlareBundle\Query\TableAliasRegistry;
use HeimrichHannot\FlareBundle\Registry\Descriptor\ListTypeDescriptor;
use HeimrichHannot\FlareBundle\Registry\ListTypeRegistry;
use HeimrichHannot\FlareBundle\List\ListSpec;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class ListExecutionContextFactory
{
    public function __construct(
        private ListTypeRegistry         $listTypeRegistry,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * @throws FlareException
     */
    public function create(ListSpec $list): ListExecutionContext
    {
        $listTypeDescriptor = null;
        $listType = $list->getTypeInstance();

        if (!$listType)
        {
            $listTypeDescriptor = $this->listTypeRegistry->get($list->getTypeAlias());
            if (!$listTypeDescriptor instanceof ListTypeDescriptor) {
                throw new FlareException(
                    \sprintf('No list type registered for type "%s".', $list->getTypeAlias() ?? ''),
                    method: __METHOD__,
                );
            }

            $listType = $listTypeDescriptor->getService();
        }

        if (!$mainTable = $list->dc ?: $listTypeDescriptor?->getDataContainer()) {
            throw new FlareException('No data container table set.', method: __METHOD__);
        }

        $registry = new TableAliasRegistry();
        $registry->setMainTable($mainTable);

        $struct = (new SqlQueryStruct())
            ->setFrom($mainTable)
            ->setFromAlias(TableAliasRegistry::ALIAS_MAIN)
            ->setSelect([TableAliasRegistry::ALIAS_MAIN . '.*'])
            ->setGroupBy([TableAliasRegistry::ALIAS_MAIN . '.id']);

        if ($listType instanceof BuildQueryContract) {
            $listType->buildTableRegistry($registry);
            $listType->buildBaseQuery($struct);
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