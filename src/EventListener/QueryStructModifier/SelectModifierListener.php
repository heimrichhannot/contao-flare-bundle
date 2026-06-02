<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\EventListener\QueryStructModifier;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use HeimrichHannot\FlareBundle\Event\ModifyListQueryStructEvent;
use HeimrichHannot\FlareBundle\Query\TableAliasRegistry;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(priority: 490)]
readonly class SelectModifierListener
{
    public function __construct(
        private Connection $connection,
    ) {}

    /**
     * @throws DBALException
     */
    public function __invoke(ModifyListQueryStructEvent $event): void
    {
        $struct = $event->queryStruct;

        if ($event->config->isCounting)
        {
            $struct->setSelect(\sprintf(
                "COUNT(DISTINCT(%s)) AS %s",
                $this->connection->quoteIdentifier(TableAliasRegistry::ALIAS_MAIN . '.id'),
                $this->connection->quoteIdentifier('count'),
            ));

            $struct->setGroupBy(null);

            return;
        }

        if ($event->config->onlyId)
        {
            $struct->setSelect(\sprintf(
                "%s AS %s",
                $this->connection->quoteIdentifier(TableAliasRegistry::ALIAS_MAIN . '.id'),
                $this->connection->quoteIdentifier('id')
            ));
        }
    }
}