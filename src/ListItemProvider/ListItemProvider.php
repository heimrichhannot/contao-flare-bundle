<?php /** @noinspection PhpFullyQualifiedNameUsageInspection */

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\ListItemProvider;

use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Dto\ContentContext;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterContextCollection;
use HeimrichHannot\FlareBundle\List\ListQueryBuilder;
use HeimrichHannot\FlareBundle\Manager\ListQueryManager;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use HeimrichHannot\FlareBundle\SortDescriptor\SortDescriptor;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ListItemProvider extends AbstractListItemProvider
{
    /**
     * @throws FilterException
     * @throws \Doctrine\DBAL\Exception
     */
    public function fetchEntries(
        ListQueryBuilder        $listQueryBuilder,
        FilterContextCollection $filters,
        ?SortDescriptor         $sortDescriptor = null,
        ?Paginator              $paginator = null,
    ): array {
        $entries = $this->fetchEntriesOrIds(
            listQueryBuilder: $listQueryBuilder,
            filters: $filters,
            sortDescriptor: $sortDescriptor,
            paginator: $paginator,
            returnIds: false,
        );

        $table = $filters->getTable();

        return \array_combine(
            \array_map(
                static fn (string $id): string => \sprintf('%s.%d', $table, $id),
                \array_column($entries, 'id')
            ),
            $entries
        );
    }

    /**
     * @throws FilterException
     * @throws \Doctrine\DBAL\Exception
     */
    public function fetchIds(
        ListQueryBuilder        $listQueryBuilder,
        FilterContextCollection $filters,
        ?SortDescriptor         $sortDescriptor = null,
        ?Paginator              $paginator = null,
    ): array {
        return $this->fetchEntriesOrIds(
            listQueryBuilder: $listQueryBuilder,
            filters: $filters,
            sortDescriptor: $sortDescriptor,
            paginator: $paginator,
            returnIds: true,
        );
    }

    /**
     * @throws FilterException
     * @throws \Doctrine\DBAL\Exception
     */
    protected function fetchEntriesOrIds(
        ListQueryBuilder        $listQueryBuilder,
        FilterContextCollection $filters,
        ?SortDescriptor         $sortDescriptor = null,
        ?Paginator              $paginator = null,
        ?bool                   $returnIds = null,
    ): array {
        $returnIds ??= false;

        $query = $this->getListQueryManager()->populate(
            listQueryBuilder: $listQueryBuilder,
            filters: $filters,
            order: $sortDescriptor?->toSql($this->getConnection()->quoteIdentifier(...)),
            limit: $paginator?->getItemsPerPage() ?: null,
            offset: $paginator?->getOffset() ?: null,
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
     * @param ListQueryBuilder $listQueryBuilder
     * @param FilterContextCollection $filters
     * @param ContentContext $contentContext
     * @throws FilterException
     * @throws \Doctrine\DBAL\Exception
     */
    public function fetchCount(
        ListQueryBuilder $listQueryBuilder,
        FilterContextCollection $filters,
    ): int {
        $query = $this->getListQueryManager()->populate(
            listQueryBuilder: $listQueryBuilder,
            filters: $filters,
            isCounting: true
        );

        if (!$query->isAllowed()) {
            return 0;
        }

        $result = $query->execute($this->getConnection());

        $count = $result->fetchOne() ?: 0;

        $result->free();

        return $count;
    }

    protected function getConnection(): Connection
    {
        return $this->container->get(Connection::class)
            ?? throw new \RuntimeException('Connection not found');
    }

    protected function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->container->get(EventDispatcherInterface::class)
            ?? throw new \RuntimeException('EventDispatcherInterface not found');
    }

    protected function getListQueryManager(): ListQueryManager
    {
        return $this->container->get(ListQueryManager::class)
            ?? throw new \RuntimeException('ListQueryManager not found');
    }

    public static function getSubscribedServices(): array
    {
        $services = parent::getSubscribedServices();

        $services[] = Connection::class;
        $services[] = EventDispatcherInterface::class;
        $services[] = ListQueryManager::class;

        return $services;
    }
}