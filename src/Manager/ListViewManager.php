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
use HeimrichHannot\FlareBundle\List\ListQueryBuilder;
use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
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
        private readonly FilterElementRegistry    $filterElementRegistry,
        private readonly FilterFormManager        $formManager,
        private readonly ListQueryManager         $listQueryManager,
        private readonly ListItemProviderManager  $itemProvider,
        private readonly PaginatorBuilderFactory  $paginatorBuilderFactory,
        private readonly RequestStack             $requestStack,
    ) {}

    /**
     * @throws FlareException
     */
    public function getListQueryBuilder(
        ListModel      $listModel,
        ContentContext $contentContext,
    ): ListQueryBuilder {
        $cacheKey = $this->makeCacheKey($listModel, $contentContext);
        if (isset($this->listQueryBuilderCache[$cacheKey])) {
            return $this->listQueryBuilderCache[$cacheKey];
        }

        $listQueryBuilder = $this->listQueryManager->prepare($listModel);

        $this->listQueryBuilderCache[$cacheKey] = $listQueryBuilder;

        return $listQueryBuilder;
    }

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
     * Get the form for a given list model and form name.
     *
     * @param ListModel      $listModel The list model.
     * @param ContentContext $contentContext The content context.
     *
     * @return FormInterface The form.
     *
     * @throws FilterException If the request is not available.
     */
    public function getForm(ListModel $listModel, ContentContext $contentContext): FormInterface
    {
        $cacheKey = $this->makeCacheKey($listModel, $contentContext);
        if (isset($this->formCache[$cacheKey])) {
            return $this->formCache[$cacheKey];
        }

        if (!$request = $this->requestStack->getCurrentRequest()) {
            throw new FilterException('Request not available', source: __METHOD__);
        }

        $filters = $this->getFilterContextCollection($listModel, $contentContext);
        $formName = $this->makeFormName($listModel, $contentContext);
        $formAction = $this->getFormAction($contentContext);

        $form = $this->formManager->buildForm(
            filters: $filters,
            name: $formName,
            action: $formAction,
        );
        $form->handleRequest($request);

        $this->formManager->hydrateForm($filters, $form);
        $this->formManager->hydrateFilterElements($filters, $form);

        $this->formCache[$cacheKey] = $form;

        return $form;
    }

    /**
     * Get the sort descriptor for a given list model and form name.
     *
     * @param ListModel           $listModel The list model.
     * @param ContentContext      $contentContext The content context.
     * @param SortDescriptor|null $sortDescriptor A possible sort descriptor override (optional).
     *
     * @return SortDescriptor|null The sort descriptor, or null if none is found.
     *
     * @throws FlareException bubbling from {@see SortDescriptor::fromSettings()}
     */
    public function getSortDescriptor(
        ListModel       $listModel,
        ContentContext  $contentContext,
    ): ?SortDescriptor {
        $cacheKey = $this->makeCacheKey($listModel, $contentContext);
        if (isset($this->listSortCache[$cacheKey])) {
            return $this->listSortCache[$cacheKey];
        }

        if (!$listModel->sortSettings) {
            return null;
        }

        $sortSettings = StringUtil::deserialize($listModel->sortSettings);
        if (!$sortSettings || !\is_array($sortSettings)) {
            return null;
        }

        return $this->listSortCache[$cacheKey] = SortDescriptor::fromSettings($sortSettings);
    }

    /**
     * Get the paginator for a given list model, form name, and paginator configuration.
     *
     * @param ListModel       $listModel       The list model.
     * @param ContentContext  $contentContext  The content context.
     * @param PaginatorConfig $paginatorConfig The paginator configuration.
     *
     * @return Paginator The paginator.
     *
     * @throws FlareException If an error occurs while fetching the total count of entries or building the paginator.
     */
    public function getPaginator(
        ListModel       $listModel,
        ContentContext  $contentContext,
        PaginatorConfig $paginatorConfig,
    ): Paginator {
        $form = $this->getForm($listModel, $contentContext);

        if ($form->isSubmitted() && !$form->isValid()) {
            return $this->paginatorBuilderFactory->create()->buildEmpty();
        }

        $cacheKey = $this->makeCacheKey($listModel, $contentContext, (string) $paginatorConfig);
        if (isset($this->listPaginatorCache[$cacheKey])) {
            return $this->listPaginatorCache[$cacheKey];
        }

        $itemProvider = $this->itemProvider->ofListModel($listModel);

        try
        {
            $listQueryBuilder = $this->getListQueryBuilder($listModel, $contentContext);
            $filters = $this->getFilterContextCollection($listModel, $contentContext);

            $event = new FetchCountEvent(
                listModel: $listModel,
                contentContext: $contentContext,
                itemProvider: $itemProvider,
                listQueryBuilder: $listQueryBuilder,
                filters: $filters,
                form: $form,
                paginatorConfig: $paginatorConfig,
            );

            $event = $this->eventDispatcher->dispatch(
                event: $event,
                eventName: $event->getEventName(),
            );

            $itemProvider = $event->getItemProvider();
            $listQueryBuilder = $event->getListQueryBuilder();
            $filters = $event->getFilters();

            $total = $itemProvider->fetchCount($listQueryBuilder, $filters);
        }
        catch (\Exception $e)
        {
            throw new FlareException($e->getMessage(), $e->getCode(), $e);
        }

        $formName = $this->makeFormName($listModel, $contentContext);

        return $this->listPaginatorCache[$cacheKey] = $this->paginatorBuilderFactory
            ->create()
            ->fromConfig($paginatorConfig)
            ->queryPrefix($formName)
            ->handleRequest()
            ->totalItems($total)
            ->build();
    }

    /**
     * Get the entries for a given list model, form name, and optional paginator configuration.
     *
     * @param ListModel           $listModel The list model.
     * @param ContentContext      $contentContext The content context.
     * @param PaginatorConfig     $paginatorConfig The paginator configuration.
     * @param SortDescriptor|null $sortDescriptor A possible sort descriptor override (optional).
     *
     * @return array The entries as an associative array of rows from the database, indexed by their primary key.
     *
     * @throws FlareException If an error occurs while fetching the entries.
     */
    public function getEntries(
        ListModel       $listModel,
        ContentContext  $contentContext,
        PaginatorConfig $paginatorConfig,
        ?SortDescriptor $sortDescriptor = null,
    ): array {
        $form = $this->getForm($listModel, $contentContext);

        if ($form->isSubmitted() && !$form->isValid())
        {
            return [];
        }

        $cacheKey = $this->makeCacheKey($listModel, $contentContext, (string) $paginatorConfig);
        if (isset($this->listEntriesCache[$cacheKey]))
        {
            return $this->listEntriesCache[$cacheKey];
        }

        $itemProvider = $this->itemProvider->ofListModel($listModel);

        try
        {
            $listQueryBuilder = $this->getListQueryBuilder($listModel, $contentContext);
            $filters          = $this->getFilterContextCollection($listModel, $contentContext);
            $paginator        = $this->getPaginator($listModel, $contentContext, $paginatorConfig);

            $event = new FetchListEntriesEvent(
                listModel: $listModel,
                contentContext: $contentContext,
                itemProvider: $itemProvider,
                listQueryBuilder: $listQueryBuilder,
                filters: $filters,
                form: $form,
                paginatorConfig: $paginator,
                sortDescriptor: $sortDescriptor,
            );

            $event = $this->eventDispatcher->dispatch(
                event: $event,
                eventName: $event->getEventName(),
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

            $this->cacheEntries($listModel, $contentContext, $entries);
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

    protected function cacheEntries(ListModel $listModel, ContentContext $contentContext, array $rows): void
    {
        foreach ($rows as $row)
        {
            if (!$id = (int) ($row['id'] ?? 0)) {
                continue;
            }

            $cacheKey = $this->makeCacheKey($listModel, $contentContext, "entry.{$id}");

            $this->entryCache[$cacheKey] = $row;
        }
    }

    /**
     * @throws FlareException
     */
    public function getEntry(int $id, ListModel $listModel, ContentContext $contentContext): ?array
    {
        $cacheKey = $this->makeCacheKey($listModel, $contentContext, "entry.{$id}");
        if (isset($this->entryCache[$cacheKey])) {
            return $this->entryCache[$cacheKey];
        }

        $itemProvider = $this->itemProvider->ofListModel($listModel);
        $listQueryBuilder = $this->getListQueryBuilder($listModel, $contentContext);
        $filters = $this->getFilterContextCollection($listModel, $contentContext, $id);

        $idDefinition = SimpleEquationElement::define(
            equationLeft: 'id',
            equationOperator: SqlEquationOperator::EQUALS,
            equationRight: $id,
        )->setAlias('_flare_id', $ogAlias);

        $idFilterContext = $this->filterContextManager->definitionToContext(
            definition: $idDefinition,
            listModel: $filters->getListModel(),
            contentContext: $contentContext,
            descriptor: $this->filterElementRegistry->get($ogAlias),
        );

        /**
         * @noinspection PhpParenthesesCanBeOmittedForNewCallInspection
         * @noinspection RedundantSuppression
         */
        $event = (new FetchListEntriesEvent(
            listModel: $listModel,
            contentContext: $contentContext,
            itemProvider: $itemProvider,
            listQueryBuilder: $listQueryBuilder,
            filters: $filters,
        ))->withSingleEntryConfig(new FetchSingleEntryConfig($id, $idFilterContext));

        /** @var FetchListEntriesEvent $event */
        $event = $this->eventDispatcher->dispatch(
            event: $event,
            eventName: $event->getEventName(),
        );

        $itemProvider = $event->getItemProvider();
        $listQueryBuilder = $event->getListQueryBuilder();
        $filters = $event->getFilters();

        if (!$idFilterContext = $event->getSingleEntryConfig()?->idFilterContext) {
            return null;
        }

        $filters->add($idFilterContext);

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
     * @param ListModel       $listModel The list model.
     * @param ContentContext  $contentContext The content context.
     *
     * @return Model The model.
     *
     * @throws FlareException If the model class does not exist or the entry ID is invalid.
     */
    public function getModel(
        int             $id,
        ListModel       $listModel,
        ContentContext  $contentContext,
    ): Model {
        $registry = Model\Registry::getInstance();
        if ($model = $registry->fetch($listModel->dc, $id))
            // Contao native model cache
        {
            return $model;
        }

        $modelClass = Model::getClassFromTable($listModel->dc);
        if (!\class_exists($modelClass)) {
            throw new FlareException(\sprintf('Model class does not exist: "%s"', $modelClass), source: __METHOD__);
        }

        if (!$row = $this->getEntry(id: $id, listModel: $listModel, contentContext: $contentContext)) {
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
            listModel: $listModel,
            contentContext: $contentContext
        );

        if (!$autoItem = (string) CallbackHelper::tryGetProperty($model, $autoItemField)) {
            return null;
        }

        if (!$page = PageModel::findByPk($pageId)) {
            throw new FlareException(\sprintf('Details page not found [ID %s]', $pageId), source: __METHOD__);
        }

        $url = $page->getAbsoluteUrl('/' . $autoItem);

        $event = new ListViewDetailsPageUrlGeneratedEvent(
            listModel: $listModel,
            contentContext: $contentContext,
            model: $model,
            autoItem: $autoItem,
            page: $page,
            url: $url,
        );

        $event = $this->eventDispatcher->dispatch(
            event: $event,
            eventName: $event->getEventName(),
        );

        return $event->getUrl();
    }

    public function getFormAction(ContentContext $contentContext): ?string
    {
        if (!$jumpTo = $contentContext->getActionPage()) {
            return null;
        }

        if (!$pageModel = PageModel::findByPk($jumpTo)) {
            return null;
        }

        return $pageModel->getAbsoluteUrl();
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

    /**
     * Automatically generate a form name for a given list model if none is provided.
     *
     * @param ListModel      $listModel The list model.
     * @param ContentContext $contentContext The content context.
     *
     * @return string The form name.
     */
    public function makeFormName(ListModel $listModel, ContentContext $contentContext): string
    {
        return $contentContext->getFormName() ?: 'fl' . $listModel->id;
    }
}