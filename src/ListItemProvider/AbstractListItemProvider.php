<?php

namespace HeimrichHannot\FlareBundle\ListItemProvider;

use HeimrichHannot\FlareBundle\Dto\ContentContext;
use HeimrichHannot\FlareBundle\FilterElement\SimpleEquationElement;
use HeimrichHannot\FlareBundle\Filter\FilterContextCollection;
use HeimrichHannot\FlareBundle\List\ListQueryBuilder;
use HeimrichHannot\FlareBundle\Manager\FilterContextManager;
use HeimrichHannot\FlareBundle\Enum\SqlEquationOperator;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

/**
 * Abstract class for list item providers.
 *
 * This class provides a default implementation for {@see ListItemProvider::fetchEntry()} that caches the results.
 */
abstract class AbstractListItemProvider implements ListItemProviderInterface, ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;

    protected array $entryCache = [];

    /**
     * Fetch a single entry by its ID. Caches the result for future calls.
     */
    public function fetchEntry(
        int                     $id,
        ListQueryBuilder        $listQueryBuilder,
        FilterContextCollection $filters,
        ContentContext          $contentContext
    ): ?array {
        if (isset($this->entryCache[$cacheKey = "{$filters->getTable()}.$id"])) {
            return $this->entryCache[$cacheKey];
        }

        if (!$filterContextManager = $this->container->get(FilterContextManager::class)) {
            throw new \RuntimeException('FilterContextManager not found');
        }

        $idFilterContext = $filterContextManager->definitionToContext(
            definition: SimpleEquationElement::define('id', SqlEquationOperator::EQUALS, $id),
            listModel: $filters->getListModel(),
            contentContext: $contentContext,
        );

        $filters->add($idFilterContext);

        $entries = $this->fetchEntries(listQueryBuilder: $listQueryBuilder, filters: $filters);

        return $this->entryCache[$cacheKey] = $entries[\array_key_first($entries)] ?? null;
    }

    public static function getSubscribedServices(): array
    {
        return [
            FilterContextManager::class,
        ];
    }
}