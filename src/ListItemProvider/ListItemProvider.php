<?php /** @noinspection PhpFullyQualifiedNameUsageInspection */

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\ListItemProvider;

use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterContextCollection;
use HeimrichHannot\FlareBundle\Manager\FilterContextManager;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use HeimrichHannot\FlareBundle\SortDescriptor\SortDescriptor;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ListItemProvider extends AbstractListItemProvider
{
    use ListFilterTrait;

    public function __construct(
        private readonly Connection               $connection,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly FilterContextManager     $filterContextManager,
    ) {
        parent::__construct($filterContextManager);
    }

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
        FilterContextCollection $filters,
        ?SortDescriptor         $sortDescriptor = null,
        ?Paginator              $paginator = null,
    ): array {
        $entries = $this->fetchEntriesOrIds(
            filters: $filters,
            sortDescriptor: $sortDescriptor,
            paginator: $paginator,
            returnIds: false,
        );

        $table = $filters->getTable();

        $entries = \array_combine(
            \array_map(fn ($id) => "$table.$id", \array_column($entries, 'id')),
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
        FilterContextCollection $filters,
        ?SortDescriptor         $sortDescriptor = null,
        ?Paginator              $paginator = null,
    ): array {
        return $this->fetchEntriesOrIds(
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
        FilterContextCollection $filters,
        ?SortDescriptor         $sortDescriptor = null,
        ?Paginator              $paginator = null,
        ?bool                   $returnIds = null,
    ): array {
        $returnIds ??= false;

        $dto = $this->buildFilteredQuery(
            filters: $filters,
            order: $sortDescriptor?->toSql(),
            limit: $paginator?->getItemsPerPage() ?: null,
            offset: $paginator?->getOffset() ?: null,
            onlyId: $returnIds,
        );

        if (!$dto->isAllowed())
        {
            return [];
        }

        $result = $this->connection->executeQuery($dto->getQuery(), $dto->getParams(), $dto->getTypes());

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
    public function fetchCount(FilterContextCollection $filters): int
    {
        $dto = $this->buildFilteredQuery($filters, isCounting: true);

        if (!$dto->isAllowed()) {
            return 0;
        }

        $result = $this->connection->executeQuery($dto->getQuery(), $dto->getParams(), $dto->getTypes());

        $count = $result->fetchOne() ?: 0;

        $result->free();

        return $count;
    }
}