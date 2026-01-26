<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Manager;

use Contao\Model;
use Contao\PageModel;
use Contao\StringUtil;
use HeimrichHannot\FlareBundle\DataContainer\ListContainer;
use HeimrichHannot\FlareBundle\Dto\ContentContext;
use HeimrichHannot\FlareBundle\Dto\FetchSingleEntryConfig;
use HeimrichHannot\FlareBundle\Enum\SqlEquationOperator;
use HeimrichHannot\FlareBundle\Event\FetchCountEvent;
use HeimrichHannot\FlareBundle\Event\FetchListEntriesEvent;
use HeimrichHannot\FlareBundle\Event\ListViewDetailsPageUrlGeneratedEvent;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\FilterContextCollection;
use HeimrichHannot\FlareBundle\Factory\PaginatorBuilderFactory;
use HeimrichHannot\FlareBundle\FilterElement\SimpleEquationElement;
use HeimrichHannot\FlareBundle\List\ListDefinition;
use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\SortDescriptor\SortDescriptor;
use HeimrichHannot\FlareBundle\Util\CallbackHelper;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Class ListViewManager
 *
 * Manages the list view, including filters, forms, pagination, sort-order, and entries.
 */
final class ListViewManager
{
    protected array $entryCache = [];
    protected array $filterContextCache = [];
    protected array $formCache = [];
    protected array $listQueryBuilderCache = [];
    protected array $listEntriesCache = [];
    protected array $listSortCache = [];
    protected array $listPaginatorCache = [];

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly FilterContextManager     $filterContextManager,
        private readonly ListQueryManager         $listQueryManager,
        private readonly ListItemProviderManager  $itemProvider,
    ) {}

    /**
     * Get the filter context collection for a given list model and form name.
     * The form name ist required to cache the filter context collection, which is dependent on a filter form instance.
     *
     * @param ListModel      $listModel The list model.
     * @param ContentContext $contentContext The content context.
     *
     * @return FilterContextCollection The filter context collection.
     *
     * @throws FilterException If the list model is not published or setup is incomplete.
     */
    public function getFilterContextCollection(
        ListModel      $listModel,
        ContentContext $contentContext,
        ?int           $entryId = null,
    ): FilterContextCollection {
        $cacheKey = $this->makeCacheKey($listModel, $contentContext, $entryId ? "entry_{$entryId}" : '');
        if (isset($this->filterContextCache[$cacheKey])) {
            return $this->filterContextCache[$cacheKey];
        }

        if (!$listModel->published) {
            throw new FilterException("List model not published [ID {$listModel->id}]", source: __METHOD__);
        }

        if (!$filters = $this->filterContextManager->collect($listModel, $contentContext)) {
            throw new FilterException("List model setup incomplete [ID {$listModel->id}]", source: __METHOD__);
        }

        $this->filterContextCache[$cacheKey] = $filters;

        return $filters;
    }

    /**
     * Get the sort descriptor for a given list model and form name.
     *
     * @return SortDescriptor|null The sort descriptor, or null if none is found.
     *
     * @throws FlareException bubbling from {@see SortDescriptor::fromSettings()}
     */
    public function getSortDescriptor(ListDefinition $listDefinition): ?SortDescriptor
    {
        if (!$listDefinition->sortSettings) {
            return null;
        }

        $sortSettings = StringUtil::deserialize($listDefinition->sortSettings);
        if (!$sortSettings || !\is_array($sortSettings)) {
            return null;
        }

        return SortDescriptor::fromSettings($sortSettings);
    }


    /**
     * Get the entries for a given list model, form name, and optional paginator configuration.
     *
     * @param ListModel           $listDefinition The list model.
     * @param ContentContext      $contentContext The content context.
     * @param PaginatorConfig     $paginatorConfig The paginator configuration.
     * @param SortDescriptor|null $sortDescriptor A possible sort descriptor override (optional).
     *
     * @return array The entries as an associative array of rows from the database, indexed by their primary key.
     *
     * @throws FlareException If an error occurs while fetching the entries.
     */
    public function getEntries(
        ListDefinition $listDefinition,
        ContentContext $contentContext,
    ): array {
        $form = $this->getForm($listDefinition);

        if ($form->isSubmitted() && !$form->isValid())
        {
            return [];
        }

        $paginatorConfig = $contentContext->getPaginatorConfig();

        $cacheKey = $listDefinition->hash() . '.' . $paginatorConfig;
        if (isset($this->listEntriesCache[$cacheKey])) {
            return $this->listEntriesCache[$cacheKey];
        }

        $itemProvider = $this->itemProvider->ofList($listDefinition);

        try
        {
            $listQueryBuilder = $this->listQueryManager->prepare($listDefinition);
            $filters          = clone $listDefinition->getFilters();
            $paginator        = $this->getPaginator($listDefinition, $paginatorConfig);

            $event = $this->eventDispatcher->dispatch(
                new FetchListEntriesEvent(
                    listDefinition: $listDefinition,
                    itemProvider: $itemProvider,
                    listQueryBuilder: $listQueryBuilder,
                    filters: $filters,
                    form: $form,
                    paginatorConfig: $paginator,
                    sortDescriptor: $sortDescriptor,
                )
            );

            $itemProvider = $event->getItemProvider();
            $listQueryBuilder = $event->getListQueryBuilder();
            $filters = $event->getFilters();

            $entries = $itemProvider->fetchEntries(
                listQueryBuilder: $listQueryBuilder,
                filters: $filters,
                sortDescriptor: $sortDescriptor,
                paginator: $paginator,
            );

            $this->cacheEntries($listDefinition, $entries);
        }
        catch (FlareException $e)
        {
            throw $e;
        }
        catch (\Exception $e)
        {
            throw new FlareException($e->getMessage(), $e->getCode(), $e);
        }

        $this->listEntriesCache[$cacheKey] = $entries;

        return $entries;
    }

    protected function cacheEntries(ListDefinition $listDefinition, array $rows): void
    {
        $hash = $listDefinition->hash();

        foreach ($rows as $row)
        {
            if (!$id = (int) ($row['id'] ?? 0)) {
                continue;
            }

            $cacheKey = $hash . ".entry.{$id}";

            $this->entryCache[$cacheKey] = $row;
        }
    }

    /**
     * @throws FlareException
     */
    public function getEntry(int $id, ListDefinition $listDefinition): ?array
    {
        $cacheKey = $listDefinition->hash() . ".entry.{$id}";
        if (isset($this->entryCache[$cacheKey])) {
            return $this->entryCache[$cacheKey];
        }

        $itemProvider = $this->itemProvider->ofList($listDefinition);
        $listQueryBuilder = $this->listQueryManager->prepare($listDefinition);
        $filters = clone $listDefinition->getFilters();

        $idDefinition = SimpleEquationElement::define(
            equationLeft: 'id',
            equationOperator: SqlEquationOperator::EQUALS,
            equationRight: $id,
        )->setType('_flare_id', $ogAlias);

        /**
         * @noinspection PhpParenthesesCanBeOmittedForNewCallInspection
         * @noinspection RedundantSuppression
         * @var FetchListEntriesEvent $event
         */
        $event = $this->eventDispatcher->dispatch(
            (new FetchListEntriesEvent(
                listDefinition: $listDefinition,
                itemProvider: $itemProvider,
                listQueryBuilder: $listQueryBuilder,
                filters: $filters,
            ))->withSingleEntryConfig(new FetchSingleEntryConfig($id, $idDefinition))
        );

        $itemProvider = $event->getItemProvider();
        $listQueryBuilder = $event->getListQueryBuilder();
        $filters = $event->getFilters();

        $filters->add($idDefinition);

        $entries = $itemProvider->fetchEntries(
            listQueryBuilder: $listQueryBuilder,
            filters: $filters,
        );

        $entry = \reset($entries) ?: null;

        return $this->entryCache[$cacheKey] = $entry;
    }

    /**
     * Get the model of an entry's row for a given list model, form name, and entry ID.
     *
     * @param int             $id The entry ID.
     * @param ListModel       $listDefinition The list model.
     * @param ContentContext  $contentContext The content context.
     *
     * @return Model The model.
     *
     * @throws FlareException If the model class does not exist or the entry ID is invalid.
     */
    public function getModel(int $id, ListDefinition $listDefinition): Model
    {
        $registry = Model\Registry::getInstance();
        if ($model = $registry->fetch($listDefinition->dc, $id))
            // Contao native model cache
        {
            return $model;
        }

        $modelClass = Model::getClassFromTable($listDefinition->dc);
        if (!\class_exists($modelClass)) {
            throw new FlareException(\sprintf('Model class does not exist: "%s"', $modelClass), source: __METHOD__);
        }

        if (!$row = $this->getEntry(id: $id, listDefinition: $listDefinition)) {
            throw new FlareException('Invalid entry id.', source: __METHOD__);
        }

        $model = new $modelClass($row);
        if (!$model instanceof Model) {
            throw new FlareException('Invalid model instance.', source: __METHOD__);
        }

        $registry->register($model);

        return $model;
    }

    /**
     * Get the URL of the details page of a particular entry for a given list model, form name, and entry ID.
     *
     * @param int            $id The entry ID.
     * @param ListModel      $listModel The list model.
     * @param ContentContext $contentContext The content context.
     *
     * @return string|null The URL of the details page, or null if not found.
     *
     * @throws FlareException If the details page is not found.
     */
    public function getDetailsPageUrl(
        int            $id,
        ListModel      $listModel,
        ContentContext $contentContext,
    ): ?string {
        if (!$pageId = (int) ($listModel->jumpToReader ?: 0)) {
            return null;
        }

        $autoItemField = $listModel->getAutoItemField();
        $model = $this->getModel(
            id: $id,
            listDefinition: $listModel,
            contentContext: $contentContext
        );

        if (!$autoItem = (string) CallbackHelper::tryGetProperty($model, $autoItemField)) {
            return null;
        }

        if (!$page = PageModel::findByPk($pageId)) {
            throw new FlareException(\sprintf('Details page not found [ID %s]', $pageId), source: __METHOD__);
        }

        $url = $page->getAbsoluteUrl('/' . $autoItem);

        $event = $this->eventDispatcher->dispatch(
            new ListViewDetailsPageUrlGeneratedEvent(
                listModel: $listModel,
                contentContext: $contentContext,
                model: $model,
                autoItem: $autoItem,
                page: $page,
                url: $url,
            )
        );

        return $event->getUrl();
    }

    /**
     * Create a cache key for a given list model, form name, and additional arguments.
     *
     * @param ListModel      $listModel The list model.
     * @param ContentContext $context The content context.
     * @param mixed          ...$args Additional arguments that should be part of the cache key (optional).
     *
     * @return string The cache key.
     */
    public function makeCacheKey(ListModel $listModel, ?ContentContext $context, mixed ...$args): string
    {
        $args = \array_filter($args);
        $parts = [ListContainer::TABLE_NAME . '.' . $listModel->id, $context?->getUniqueId(), ...$args];
        $parts = \array_filter($parts);
        return \implode('@', $parts);
    }
}