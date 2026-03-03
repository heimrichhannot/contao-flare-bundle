<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\EventListener\QueryStructModifier;

use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Event\ModifyListQueryStructEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(priority: 470)]
readonly class ConditionsModifierListener
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function __invoke(ModifyListQueryStructEvent $event): void
    {
        $queries = $event->filterQueries;
        $registry = $event->tableAliasRegistry;
        $struct = $event->queryStruct;

        $conditions = [];
        $parameters = [];
        $types = [];

        foreach ($queries as $query)
        {
            $registry->activateAlias($query->getTargetAlias());

            if (!$sql = $query->getSql())
            {
                continue;
            }

            $conditions[] = $sql;

            foreach ($query->getParams() as $k => $v) {
                $parameters[$k] = $v;
            }

            foreach ($query->getTypes() as $k => $v) {
                $types[$k] = $v;
            }
        }

        if ($conditions)
        {
            $allConditions = \array_filter([$struct->getConditions(), ...$conditions]);

            if ($allConditions) {
                $struct->setConditions(
                    $this->connection->createExpressionBuilder()->and(...$allConditions)
                );
            }
        }

        $struct->setParams(\array_merge($struct->getParams(), $parameters));
        $struct->setTypes(\array_merge($struct->getTypes(), $types));
    }
}