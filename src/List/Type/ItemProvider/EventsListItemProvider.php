<?php

namespace HeimrichHannot\FlareBundle\List\Type\ItemProvider;

use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Dto\FilteredQueryDto;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterContextCollection;
use HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\List\ListFilterTrait;
use HeimrichHannot\FlareBundle\List\ListItemProvider;
use HeimrichHannot\FlareBundle\List\ListItemProviderInterface;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use HeimrichHannot\FlareBundle\SortDescriptor\SortDescriptor;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class EventsListItemProvider implements ListItemProviderInterface
{
    use ListFilterTrait;

    public function __construct(
        private readonly Connection               $connection,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ListItemProvider         $listItemProvider,
    ) {}

    protected function getConnection(): Connection
    {
        return $this->connection;
    }

    protected function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->eventDispatcher;
    }

    public function fetchCount(FilterContextCollection $filters): int
    {
        // TODO: Implement fetchCount() method.

        return 0;
    }

    /**
     * @throws FilterException
     */
    public function fetchEntries(
        FilterContextCollection $filters,
        ?SortDescriptor         $sortDescriptor = null,
        ?Paginator              $paginator = null,
    ): array {
        $sortDescriptor ??= SortDescriptor::fromMap([
            'startTime' => 'DESC',
            'endTime'   => 'DESC',
        ]);

        $dto = $this->buildFilteredQuery(
            filters: $filters,
            order: $sortDescriptor?->toSql(),
        );

        if (!$dto->isAllowed())
        {
            return [];
        }

        $result = $this->connection->executeQuery($dto->getQuery(), $dto->getParams(), $dto->getTypes());

        $entries = $result->fetchAllAssociative();

        if (\count($entries) > 40)
        {
            $entries = \array_slice($entries, 0, 40);
        }

        $entries = \array_combine(\array_column($entries, 'id'), $entries);

        $result->free();

        return $entries;
    }

    public function fetchIds(
        FilterContextCollection $filters,
        ?SortDescriptor         $sortDescriptor = null,
        ?Paginator              $paginator = null,
    ): array {
        // TODO: Implement fetchIds() method.

        return [];
    }
}