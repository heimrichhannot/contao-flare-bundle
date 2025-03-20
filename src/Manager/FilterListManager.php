<?php /** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace HeimrichHannot\FlareBundle\Manager;

use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterContextCollection;
use HeimrichHannot\FlareBundle\Paginator\Builder\PaginatorBuilderFactory;
use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use HeimrichHannot\FlareBundle\Model\ListModel;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class FilterListManager
{
    protected array $filterContextCache = [];
    protected array $formCache = [];
    protected array $entriesCache = [];
    protected array $paginationCache = [];

    public function __construct(
        private readonly FilterContextManager    $contextManager,
        private readonly PaginatorBuilderFactory $paginatorBuilderFactory,
        private readonly RequestStack            $requestStack,
    ) {}

    /**
     * @throws FilterException
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
     * @throws FilterException
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

        $form = $this->contextManager->buildForm($filters, $formName);
        $form->handleRequest($request);

        $this->contextManager->hydrateForm($filters, $form);
        $this->contextManager->hydrateFilterElements($filters, $form);

        $this->formCache[$cacheKey] = $form;

        return $form;
    }

    /**
     * @throws FilterException
     * @throws \Doctrine\DBAL\Exception
     */
    public function getEntries(ListModel $listModel, string $formName, PaginatorConfig $paginatorConfig): array
    {
        $form = $this->getForm($listModel, $formName);

        if ($form->isSubmitted() && !$form->isValid()) {
            return [];
        }

        $cacheKey = $this->makeCacheKey($listModel, $formName);
        if (isset($this->entriesCache[$cacheKey])) {
            return $this->entriesCache[$cacheKey];
        }

        $filters = $this->getFilterContextCollection($listModel, $formName);

        $pagination = $this->getPaginator($listModel, $formName, $paginatorConfig);

        $entries = $this->contextManager->fetchEntries($filters, $pagination);

        $this->entriesCache[$cacheKey] = $entries;

        return $entries;
    }

    /**
     * @throws FilterException
     * @throws \Doctrine\DBAL\Exception
     */
    public function getPaginator(ListModel $listModel, string $formName, PaginatorConfig $paginatorConfig): Paginator
    {
        $form = $this->getForm($listModel, $formName);

        if ($form->isSubmitted() && !$form->isValid()) {
            return $this->paginatorBuilderFactory->create()->buildEmpty();
        }

        $cacheKey = $this->makeCacheKey($listModel, $formName);
        if (isset($this->paginationCache[$cacheKey])) {
            return $this->paginationCache[$cacheKey];
        }

        $filters = $this->getFilterContextCollection($listModel, $formName);
        $total = $this->contextManager->fetchCount($filters);

        return $this->paginatorBuilderFactory
            ->create()
            ->fromConfig($paginatorConfig)
            ->queryPrefix($formName)
            ->handleRequest()
            ->totalItems($total)
            ->build();
    }

    public function makeCacheKey(ListModel $listModel, string $formName, ...$args): string
    {
        $parts = [$listModel->id, $formName, ...$args];
        return \implode('@', $parts);
    }

    public function makeFormName(ListModel $listModel, ?string $formName = null): string
    {
        return $formName ?: 'fl' . $listModel->id;
    }
}