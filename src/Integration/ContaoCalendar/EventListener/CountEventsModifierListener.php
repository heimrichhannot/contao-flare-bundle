<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Integration\ContaoCalendar\EventListener;

use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Event\ModifyListQueryStructEvent;
use HeimrichHannot\FlareBundle\Exception\AbortFilteringException;
use HeimrichHannot\FlareBundle\Query\SqlQueryStruct;
use HeimrichHannot\FlareBundle\Query\TableAliasRegistry;
use HeimrichHannot\FlareBundle\Util\Str;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(priority: 100)]
readonly class CountEventsModifierListener
{
    public function __construct(
        private Connection $connection,
    ) {}

    /**
     * @throws AbortFilteringException
     */
    public function __invoke(ModifyListQueryStructEvent $event): void
    {
        if (!$event->config->isCounting) {
            return;
        }

        $overrideSelect = $event->config->attributes['ContaoCalendar_overrideSelect'] ?? null;

        if (!\is_array($overrideSelect)) {
            return;
        }

        $overrideSelect = \array_unique(\array_filter(\array_map(function (string $name): ?string {
            if (!$name = \trim($name)) {
                return null;
            }

            $parts = \explode('.', $name);

            foreach ($parts as $part) {
                if (!Str::isValidSqlName($part)) {
                    return null;
                }
            }

            if (\count($parts) > 1) {
                return $this->connection->quoteIdentifier($name);
            }

            return $this->connection->quoteIdentifier(TableAliasRegistry::ALIAS_MAIN . '.' . $name);
        }, $overrideSelect)));

        if (!$overrideSelect) {
            throw new AbortFilteringException('No columns selected.', method: __METHOD__);
        }

        $event->queryStruct->setSelect($overrideSelect);
    }
}