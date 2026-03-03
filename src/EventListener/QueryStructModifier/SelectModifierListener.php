<?php

namespace HeimrichHannot\FlareBundle\EventListener\QueryStructModifier;

use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Event\ModifyListQueryStructEvent;
use HeimrichHannot\FlareBundle\Exception\AbortFilteringException;
use HeimrichHannot\FlareBundle\Query\TableAliasRegistry;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(priority: 490)]
readonly class SelectModifierListener
{
    public function __construct(
        private Connection $connection,
    ) {}

    /**
     * @throws AbortFilteringException
     */
    public function __invoke(ModifyListQueryStructEvent $event): void
    {
        $struct = $event->queryStruct;

        if ($event->config->isCounting)
        {
            $struct->setSelect(\sprintf(
                "COUNT(%s) AS %s",
                (\count($struct->getJoins()) < 1)
                    ? '*'
                    : \sprintf('DISTINCT(%s)', $this->connection->quoteIdentifier(TableAliasRegistry::ALIAS_MAIN . '.id')),
                $this->connection->quoteIdentifier('count')
            ));

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