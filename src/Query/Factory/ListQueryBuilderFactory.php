<?php

namespace HeimrichHannot\FlareBundle\Query\Factory;

use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Contract\ListType\PrepareListQueryInterface;
use HeimrichHannot\FlareBundle\Event\ListQueryPrepareEvent;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Query\ListQueryBuilder;
use HeimrichHannot\FlareBundle\Registry\Descriptor\ListTypeDescriptor;
use HeimrichHannot\FlareBundle\Registry\ListTypeRegistry;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class ListQueryBuilderFactory
{
    public function __construct(
        private Connection               $connection,
        private ListTypeRegistry         $listTypeRegistry,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function create(ListSpecification $list): ListQueryBuilder
    {
        /** @var ListTypeDescriptor $listTypeDescriptor */
        if (!($listTypeDescriptor = $this->listTypeRegistry->get($list->type)) instanceof ListTypeDescriptor) {
            throw new FlareException(\sprintf('No list type registered for type "%s".', $list->type), method: __METHOD__);
        }

        if (!$mainTable = $list->dc ?? $listTypeDescriptor->getDataContainer()) {
            throw new FlareException('No data container table set.', method: __METHOD__);
        }

        $builder = new ListQueryBuilder(
            connection: $this->connection,
            mainTable: $mainTable,
            mainAlias: ListQueryBuilder::ALIAS_MAIN,
        );

        $builder->select('*', of: ListQueryBuilder::ALIAS_MAIN, allowAsterisk: true);
        $builder->groupBy('id', ListQueryBuilder::ALIAS_MAIN);

        $event = new ListQueryPrepareEvent(listSpecification: $list, listQueryBuilder: $builder);

        $listType = $listTypeDescriptor->getService();
        if ($listType instanceof PrepareListQueryInterface) {
            $listType->onListQueryPrepareEvent($event);
        }

        /** @var ListQueryPrepareEvent $event */
        $event = $this->eventDispatcher->dispatch($event);

        $builder = $event->getListQueryBuilder();

        $builder->select('id', of: ListQueryBuilder::ALIAS_MAIN, as: 'id');

        return $builder;
    }
}