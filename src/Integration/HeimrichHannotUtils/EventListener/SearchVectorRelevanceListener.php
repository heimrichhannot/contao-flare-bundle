<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Integration\HeimrichHannotUtils\EventListener;

use Doctrine\DBAL\ParameterType;
use HeimrichHannot\FlareBundle\Event\ModifyListQueryStructEvent;
use HeimrichHannot\FlareBundle\Integration\HeimrichHannotUtils\FilterElement\SearchVectorElement;
use HeimrichHannot\FlareBundle\Query\TableAliasRegistry;
use HeimrichHannot\UtilsBundle\SearchVector\SearchVectorQuery;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Sorts the list by the relevance of the search vector filters with "sort by relevance" enabled, before the sort order
 * of the list (set by the OrderModifierListener with a higher priority).
 */
#[AsEventListener(priority: 410)]
readonly class SearchVectorRelevanceListener
{
    public function __construct(
        private SearchVectorQuery $searchVectorQuery,
    ) {}

    public function __invoke(ModifyListQueryStructEvent $event): void
    {
        if ($event->config->isCounting) {
            return;
        }

        $orderBy = [];
        $parameters = [];

        foreach ($event->config->list->getFilters()->all() as $key => $filter)
        {
            if (SearchVectorElement::TYPE !== $filter->getType() || !$filter->sortByRelevance) {
                continue;
            }

            $value = SearchVectorElement::normalizeSearch($event->config->filterValues[$key] ?? null);
            if (null === $value) {
                continue;
            }

            // The search vector filter is not targeted, it always filters the main table
            $relevance = $this->searchVectorQuery->createRelevance(
                table: $event->config->list->dc,
                search: $value,
                alias: TableAliasRegistry::ALIAS_MAIN,
                parameterPrefix: 'search_vector_relevance_' . \count($orderBy),
            );

            if (!$relevance) {
                continue;
            }

            [$sql, $relevanceParameters] = $relevance;

            $orderBy[] = [$sql, 'DESC'];
            $parameters = \array_merge($parameters, $relevanceParameters);
        }

        if (!$orderBy) {
            return;
        }

        // Records of the same relevance and sort order are sorted by id, otherwise pagination can repeat or skip them
        $orderBy = [...$orderBy, ...($event->queryStruct->getOrderBy() ?? [])];
        $orderBy[] = [TableAliasRegistry::ALIAS_MAIN . '.id', 'ASC'];

        $types = \array_fill_keys(\array_keys($parameters), ParameterType::STRING);

        $struct = $event->queryStruct;
        $struct->setOrderBy($orderBy);
        $struct->setParams(\array_merge($struct->getParams(), $parameters));
        $struct->setTypes(\array_merge($struct->getTypes(), $types));
    }
}
