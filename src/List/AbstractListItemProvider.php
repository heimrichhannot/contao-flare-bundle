<?php

namespace HeimrichHannot\FlareBundle\List;

use HeimrichHannot\FlareBundle\Dto\ContentContext;
use HeimrichHannot\FlareBundle\Filter\Element\SimpleEquationElement;
use HeimrichHannot\FlareBundle\Filter\FilterContextCollection;
use HeimrichHannot\FlareBundle\Manager\FilterContextManager;
use HeimrichHannot\FlareBundle\Util\SqlEquationOperator;

/**
 * Abstract class for list item providers.
 *
 * This class provides a default implementation for {@see ListItemProvider::fetchEntry()} that caches the results.
 */
abstract class AbstractListItemProvider implements ListItemProviderInterface
{
    protected array $entryCache = [];

    public function __construct(
        private readonly FilterContextManager $filterContextManager,
    ) {}

    /**
     * Fetch a single entry by its ID. Caches the result for future calls.
     */
    public function fetchEntry(int $id, FilterContextCollection $filters, ContentContext $contentContext): ?array
    {
        if (isset($this->entryCache[$cacheKey = "{$filters->getTable()}.$id"])) {
            return $this->entryCache[$cacheKey];
        }

        $idFilterContext = $this->filterContextManager->definitionToContext(
            definition: SimpleEquationElement::define('id', SqlEquationOperator::EQUALS, $id),
            listModel: $filters->getListModel(),
            contentContext: $contentContext,
        );

        $filters->add($idFilterContext);

        $entries = $this->fetchEntries(filters: $filters);

        return $this->entryCache[$cacheKey] = $entries[\array_key_first($entries)] ?? null;
    }
}