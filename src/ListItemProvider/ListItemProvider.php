<?php /** @noinspection PhpFullyQualifiedNameUsageInspection */

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\ListItemProvider;

use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterContextCollection;
use HeimrichHannot\FlareBundle\Dto\SqlQuery;
use HeimrichHannot\FlareBundle\Manager\FilterContextManager;
use HeimrichHannot\FlareBundle\Manager\ListQueryManager;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use HeimrichHannot\FlareBundle\SortDescriptor\SortDescriptor;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ListItemProvider extends AbstractListItemProvider
{
    public function __construct(
        private readonly Connection               $connection,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ListQueryManager         $listQueryManager,
    ) {}

    protected function getConnection(): Connection
    {
        return $this->connection;
    }

    protected function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->eventDispatcher;
    }

    /**
     * @throws FilterException
     * @throws \Doctrine\DBAL\Exception
     */
    public function fetchEntries(
        SqlQuery                $listQuery,
        FilterContextCollection $filters,
        ?SortDescriptor         $sortDescriptor = null,
        ?Paginator              $paginator = null,
    ): array {
        $entries = $this->fetchEntriesOrIds(
            listQuery: $listQuery,
            filters: $filters,
            sortDescriptor: $sortDescriptor,
            paginator: $paginator,
            returnIds: false,
        );

        $table = $filters->getTable();

        $entries = \array_combine(
            \array_map(
                static fn ($id) => \sprintf('%s.%d', $table, $id),
                \array_column($entries, 'id')
            ),
            $entries
        );

        $this->entryCache += $entries;

        return $entries;
    }

    /**
     * @throws FilterException
     * @throws \Doctrine\DBAL\Exception
     */
    public function fetchIds(
        SqlQuery                $listQuery,
        FilterContextCollection $filters,
        ?SortDescriptor         $sortDescriptor = null,
        ?Paginator              $paginator = null,
    ): array {
        return $this->fetchEntriesOrIds(
            listQuery: $listQuery,
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
        SqlQuery                $listQuery,
        FilterContextCollection $filters,
        ?SortDescriptor         $sortDescriptor = null,
        ?Paginator              $paginator = null,
        ?bool                   $returnIds = null,
    ): array {
        $returnIds ??= false;

        $query = $this->listQueryManager->populate(
            listQuery: $listQuery,
            filters: $filters,
            order: $sortDescriptor?->toSql(),
            limit: $paginator?->getItemsPerPage() ?: null,
            offset: $paginator?->getOffset() ?: null,
            onlyId: $returnIds,
        );

        if (!$query->isAllowed())
        {
            return [];
        }

        $result = $query->execute($this->connection);

        if ($returnIds) {
            $entries = \array_unique($result->fetchFirstColumn());
        } else {
            $entries = $result->fetchAllAssociative();
        }

        $result->free();

        return $entries;
    }

    /**
     * @throws FilterException
     * @throws \Doctrine\DBAL\Exception
     */
    public function fetchCount(SqlQuery $listQuery, FilterContextCollection $filters): int
    {
        $query = $this->listQueryManager->populate(
            listQuery: $listQuery,
            filters: $filters,
            isCounting: true
        );

        if (!$query->isAllowed()) {
            return 0;
        }

        $result = $query->execute($this->connection);

        $count = $result->fetchOne() ?: 0;

        $result->free();

        return $count;
    }
}