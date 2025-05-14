<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Manager;

use Contao\Model;
use Contao\PageModel;
use Contao\StringUtil;
use HeimrichHannot\FlareBundle\Contract\Config\ListItemProviderConfig;
use HeimrichHannot\FlareBundle\Contract\ListType\ListItemProviderContract;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\FilterContextCollection;
use HeimrichHannot\FlareBundle\List\ListItemProvider;
use HeimrichHannot\FlareBundle\List\ListItemProviderInterface;
use HeimrichHannot\FlareBundle\List\ListTypeRegistry;
use HeimrichHannot\FlareBundle\Paginator\Provider\PaginatorBuilderFactory;
use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\SortDescriptor\SortDescriptor;
use HeimrichHannot\FlareBundle\Util\CallbackHelper;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class ListViewManager
 *
 * Manages the list view, including filters, forms, pagination, sort order and entries.
 */
class ListViewManager
{
    protected array $filterContextCache = [];
    protected array $formCache = [];
    protected array $entriesCache = [];
    protected array $sortCache = [];
    protected array $paginatorCache = [];

    public function __construct(
        private readonly FilterContextManager    $contextManager,
        private readonly FilterFormManager       $formManager,
        private readonly ListItemProvider        $defaultItemProvider,
        private readonly ListTypeRegistry        $listTypeRegistry,
        private readonly PaginatorBuilderFactory $paginatorBuilderFactory,
        private readonly RequestStack            $requestStack,
    ) {}

    /**
     * Get the filter context collection for a given list model and form name.
     * The form name ist required to cache the filter context collection, which is dependent on a filter form instance.
     *
     * @param ListModel $listModel The list model.
     * @param string    $formName  The form name.
     *
     * @return FilterContextCollection The filter context collection.
     *
     * @throws FilterException If the list model is not published or setup is incomplete.
     */
    public function getFilterContextCollection(ListModel $listModel, string $formName): FilterContextCollection
    {
        $cacheKey = $this->makeCacheKey($listModel, $formName);
        if (isset($this->filterContextCache[$cacheKey])) {
            return $this->filterContextCache[$cacheKey];
        }

        if (!$listModel->published) {
            throw new FilterException("List model not published [ID $listModel->id]", source: __METHOD__);
        }

        if (!$filters = $this->contextManager->collect($listModel)) {
            throw new FilterException("List model setup incomplete [ID {$listModel->id}]", source: __METHOD__);
        }

        $this->filterContextCache[$cacheKey] = $filters;

        return $filters;
    }

    /**
     * Get the form for a given list model and form name.
     *
     * @param ListModel $listModel The list model.
     * @param string    $formName  The form name.
     *
     * @return FormInterface The form.
     *
     * @throws FilterException If the request is not available.
     */
    public function getForm(ListModel $listModel, string $formName): FormInterface
    {
        $cacheKey = $this->makeCacheKey($listModel, $formName);
        if (isset($this->formCache[$cacheKey])) {
            return $this->formCache[$cacheKey];
        }

        if (!$request = $this->requestStack->getCurrentRequest()) {
            throw new FilterException('Request not available', source: __METHOD__);
        }

        $filters = $this->getFilterContextCollection($listModel, $formName);

        $form = $this->formManager->buildForm($filters, $formName);
        $form->handleRequest($request);

        $this->formManager->hydrateForm($filters, $form);
        $this->formManager->hydrateFilterElements($filters, $form);

        $this->formCache[$cacheKey] = $form;

        return $form;
    }

    public function getSortDescriptor(
        ListModel       $listModel,
        string          $formName,
        ?SortDescriptor $sortDescriptor = null,
    ): ?SortDescriptor {
        if ($sortDescriptor instanceof SortDescriptor) {
            return $sortDescriptor;
        }

        $cacheKey = $this->makeCacheKey($listModel, $formName);
        if (isset($this->sortCache[$cacheKey])) {
            return $this->sortCache[$cacheKey];
        }

        if (!$listModel->sortSettings) {
            return null;
        }

        $sortSettings = StringUtil::deserialize($listModel->sortSettings);
        if (!$sortSettings || !\is_array($sortSettings)) {
            return null;
        }

        return $this->sortCache[$cacheKey] = SortDescriptor::fromSettings($sortSettings);
    }

    /**
     * Get the paginator for a given list model, form name, and paginator configuration.
     *
     * @param ListModel       $listModel       The list model.
     * @param string          $formName        The form name.
     * @param PaginatorConfig $paginatorConfig The paginator configuration.
     *
     * @return Paginator The paginator.
     *
     * @throws FlareException If an error occurs while fetching the total count of entries or building the paginator.
     */
    public function getPaginator(ListModel $listModel, string $formName, PaginatorConfig $paginatorConfig): Paginator
    {
        $form = $this->getForm($listModel, $formName);

        if ($form->isSubmitted() && !$form->isValid()) {
            return $this->paginatorBuilderFactory->create()->buildEmpty();
        }

        $cacheKey = $this->makeCacheKey($listModel, $formName, (string) $paginatorConfig);
        if (isset($this->paginatorCache[$cacheKey])) {
            return $this->paginatorCache[$cacheKey];
        }

        $filters = $this->getFilterContextCollection($listModel, $formName);

        try
        {
            $total = $this->defaultItemProvider->fetchCount($filters);
        }
        catch (\Exception $e)
        {
            throw new FlareException($e->getMessage(), $e->getCode(), $e);
        }

        return $this->paginatorCache[$cacheKey] = $this->paginatorBuilderFactory
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
     * @param ListModel       $listModel       The list model.
     * @param string          $formName        The form name.
     * @param PaginatorConfig $paginatorConfig The paginator configuration (optional).
     *
     * @return array The entries as an associative array of rows from the database, indexed by their primary key.
     *
     * @throws FlareException If an error occurs while fetching the entries.
     */
    public function getEntries(
        ListModel       $listModel,
        string          $formName,
        PaginatorConfig $paginatorConfig,
        ?SortDescriptor $sortDescriptor = null,
    ): array {
        $form = $this->getForm($listModel, $formName);

        if ($form->isSubmitted() && !$form->isValid())
        {
            return [];
        }

        $cacheKey = $this->makeCacheKey($listModel, $formName, (string) $paginatorConfig);
        if (isset($this->entriesCache[$cacheKey]))
        {
            return $this->entriesCache[$cacheKey];
        }

        try
        {
            $itemProvider   = $this->getListItemProvider($listModel);
            $filters        = $this->getFilterContextCollection($listModel, $formName);
            $sortDescriptor = $this->getSortDescriptor($listModel, $formName, $sortDescriptor);
            $paginator      = $this->getPaginator($listModel, $formName, $paginatorConfig);

            $entries = $itemProvider->fetchEntries(
                filters: $filters,
                sortDescriptor: $sortDescriptor,
                paginator: $paginator,
            );
        }
        catch (FlareException $e)
        {
            throw $e;
        }
        catch (\Exception $e)
        {
            throw new FlareException($e->getMessage(), $e->getCode(), $e);
        }

        $this->entriesCache[$cacheKey] = $entries;

        return $entries;
    }

    public function getListItemProvider(ListModel $listModel): ListItemProviderInterface
    {
        $service = $this->listTypeRegistry->get($listModel->type ?: null);

        if ($service instanceof ListItemProviderContract)
        {
            return $service->getListItemProvider(new ListItemProviderConfig($listModel))
                ?? $this->defaultItemProvider;
        }

        return $this->defaultItemProvider;
    }

    /**
     * Get the model of an entry's row for a given list model, form name, and entry ID.
     *
     * @param ListModel $listModel The list model.
     * @param string    $formName  The form name.
     * @param int       $id        The entry ID.
     *
     * @return Model The model.
     *
     * @throws FlareException If the model class does not exist or the entry ID is invalid.
     */
    public function getModel(
        ListModel       $listModel,
        string          $formName,
        PaginatorConfig $paginatorConfig,
        int             $id,
        ?SortDescriptor  $sortDescriptor = null,
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

        $entries = $this->getEntries(
            listModel: $listModel,
            formName: $formName,
            paginatorConfig: $paginatorConfig,
            sortDescriptor: $sortDescriptor,
        );

        if (!$row = $entries[$id] ?? null) {
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
     * @param ListModel       $listModel The list model.
     * @param string          $formName The form name.
     * @param PaginatorConfig $paginatorConfig The paginator configuration.
     * @param int             $id The entry ID.
     *
     * @return string|null The URL of the details page, or null if not found.
     *
     * @throws FlareException If the details page is not found.
     */
    public function getDetailsPageUrl(
        ListModel       $listModel,
        string          $formName,
        PaginatorConfig $paginatorConfig,
        int             $id,
    ): ?string {
        if (!$pageId = \intval($listModel->jumpToReader ?: 0)) {
            return null;
        }

        $autoItemField = $listModel->getAutoItemField();
        $model = $this->getModel($listModel, $formName, $paginatorConfig, $id);

        if (!$autoItem = CallbackHelper::tryGetProperty($model, $autoItemField)) {
            return null;
        }

        if (!$page = PageModel::findByPk($pageId)) {
            throw new FlareException(\sprintf('Details page not found [ID %s]', $pageId), source: __METHOD__);
        }

        return $page->getAbsoluteUrl('/' . $autoItem);
    }

    /**
     * Create a cache key for a given list model, form name, and additional arguments.
     *
     * @param ListModel $listModel The list model.
     * @param string    $formName  The form name.
     * @param mixed     ...$args   Additional arguments that should be part of the cache key (optional).
     *
     * @return string The cache key.
     */
    public function makeCacheKey(ListModel $listModel, string $formName, ...$args): string
    {
        $args = \array_filter($args);
        $parts = [$listModel->id, $formName, ...$args];
        return \implode('@', $parts);
    }

    /**
     * Automatically generate a form name for a given list model if none is provided.
     *
     * @param ListModel     $listModel The list model.
     * @param string|null   $formName  A provided form name (optional).
     *
     * @return string The form name.
     */
    public function makeFormName(ListModel $listModel, ?string $formName = null): string
    {
        return $formName ?: 'fl' . $listModel->id;
    }
}