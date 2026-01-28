<?php /** @noinspection PhpFullyQualifiedNameUsageInspection */

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\ListItemProvider;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use HeimrichHannot\FlareBundle\Context\ContextConfigInterface;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\List\ListQueryBuilder;
use HeimrichHannot\FlareBundle\Manager\ListQueryManager;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

class ListItemProvider extends AbstractListItemProvider
{
    /**
     * {@inheritDoc}
     *
     * @return array<int, array> Returns an array of associative arrays, each mapping column names to their values.
     *
     * @throws DBALException
     * @throws FilterException
     */
    public function fetchEntries(
        ListQueryBuilder       $listQueryBuilder,
        ListSpecification      $listDefinition,
        ContextConfigInterface $contextConfig,
    ): array {
        $entries = $this->fetchEntriesOrIds(
            listQueryBuilder: $listQueryBuilder,
            listDefinition: $listDefinition,
            contextConfig: $contextConfig,
            returnIds: false,
        );

        $table = $listDefinition->dc;

        return \array_combine(
            \array_map(
                static fn (string $id): string => \sprintf('%s.%d', $table, $id),
                \array_column($entries, 'id')
            ),
            $entries
        );
    }

    /**
     * {@inheritDoc}
     *
     * @return array<int> The IDs of all entries matching the given filters.
     *
     * @throws DBALException
     * @throws FilterException
     */
    public function fetchIds(
        ListQueryBuilder       $listQueryBuilder,
        ListSpecification      $listDefinition,
        ContextConfigInterface $contextConfig,
    ): array {
        return $this->fetchEntriesOrIds(
            listQueryBuilder: $listQueryBuilder,
            listDefinition: $listDefinition,
            contextConfig: $contextConfig,
            returnIds: true,
        );
    }

    /**
     * Unified method to fetch entries or IDs specifically for this provider.
     *
     * @throws DBALException
     * @throws FilterException
     */
    protected function fetchEntriesOrIds(
        ListQueryBuilder       $listQueryBuilder,
        ListSpecification      $listDefinition,
        ContextConfigInterface $contextConfig,
        ?bool                  $returnIds = null,
    ): array {
        $returnIds ??= false;

        $query = $this->getListQueryManager()->populate(
            listQueryBuilder: $listQueryBuilder,
            listDefinition: $listDefinition,
            contextConfig: $contextConfig,
            onlyId: $returnIds,
        );

        if (!$query->isAllowed())
        {
            return [];
        }

        $result = $query->execute($this->getConnection());

        $entries = $returnIds
            ? \array_unique($result->fetchFirstColumn())
            : $result->fetchAllAssociative();

        $result->free();

        return $entries;
    }

    /**
     * {@inheritDoc}
     *
     * @return int The total number of entries matching the given filters.
     *
     * @throws DBALException
     * @throws FilterException
     */
    public function fetchCount(
        ListQueryBuilder       $listQueryBuilder,
        ListSpecification      $listDefinition,
        ContextConfigInterface $contextConfig,
    ): int {
        $query = $this->getListQueryManager()->populate(
            listQueryBuilder: $listQueryBuilder,
            listDefinition: $listDefinition,
            contextConfig: $contextConfig,
            isCounting: true,
        );

        if (!$query->isAllowed()) {
            return 0;
        }

        $result = $query->execute($this->getConnection());

        $count = $result->fetchOne() ?: 0;

        $result->free();

        return $count;
    }

    /**
     * Returns the Doctrine DBAL connection from the service container.
     */
    protected function getConnection(): Connection
    {
        return $this->container->get(Connection::class)
            ?? throw new \RuntimeException('Connection not found');
    }

    /**
     * Returns the ListQueryManager from the service container.
     */
    protected function getListQueryManager(): ListQueryManager
    {
        return $this->container->get(ListQueryManager::class)
            ?? throw new \RuntimeException('ListQueryManager not found');
    }

    public static function getSubscribedServices(): array
    {
        $services = parent::getSubscribedServices();

        $services[] = Connection::class;
        $services[] = ListQueryManager::class;

        return $services;
    }
}