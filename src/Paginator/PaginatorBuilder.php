<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Paginator;

use HeimrichHannot\FlareBundle\Paginator\Provider\PaginatorUrlProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class PaginatorBuilder
{
    private int $currentPage = 1;
    private int $itemsPerPage = 0;
    private int $totalItems = 0;
    private ?string $queryPrefix = null;
    private ?string $routeName = null;
    private ?array $routeParams = null;

    public function __construct(
        private readonly PaginatorUrlProvider $urlProvider,
        private readonly RequestStack         $requestStack,
    ) {}

    public function fromConfig(PaginatorConfig $config): static
    {
        $this->itemsPerPage = $config->getItemsPerPage();
        $this->currentPage = $config->getCurrentPage();
        return $this;
    }

    public function currentPage(int $currentPage): static
    {
        $this->currentPage = $currentPage;
        return $this;
    }

    public function itemsPerPage(int $itemsPerPage): static
    {
        $this->itemsPerPage = $itemsPerPage;
        return $this;
    }

    public function totalItems(int $totalItems): static
    {
        $this->totalItems = $totalItems;
        return $this;
    }

    public function queryPrefix(?string $queryPrefix): static
    {
        $this->queryPrefix = $queryPrefix;
        return $this;
    }

    public function routeName(?string $routeName): static
    {
        $this->routeName = $routeName;
        return $this;
    }

    public function routeParams(?array $routeParams): static
    {
        $this->routeParams = $routeParams;
        return $this;
    }

    public function handleRequest(?Request $request = null, ?int $defaultPage = null): static
    {
        $request ??= $this->requestStack->getCurrentRequest();

        if ($request === null) {
            $this->currentPage = 1;
            return $this;
        }

        $defaultPage ??= 1;

        $this->currentPage = (int) $request->query->get(Paginator::pageParam($this->queryPrefix), $defaultPage);

        return $this;
    }

    /**
     * @return callable(int $page): string
     */
    public function makeUrlGenerator(): callable
    {
        return $this->urlProvider->createGeneratorFromRequest(
            request: $this->requestStack->getCurrentRequest(),
            routeName: $this->routeName,
            routeParams: $this->routeParams,
            queryPrefix: $this->queryPrefix,
        );
    }

    public function build(): Paginator
    {
        $lastPage = ($this->itemsPerPage > 0) ? (int) \ceil($this->totalItems / $this->itemsPerPage) : 1;
        $currentPage = (int) \max(1, ($this->currentPage < 0)
            ? ($lastPage + $this->currentPage)
            : \min($this->currentPage, $lastPage)
        );

        $firstItemNumber = ($currentPage - 1) * $this->itemsPerPage + 1;
        $lastItemNumber = (int) \max(\min($currentPage * $this->itemsPerPage, $this->totalItems), $firstItemNumber);

        $previousPage = $currentPage > 1 ? $currentPage - 1 : null;
        $nextPage = $currentPage < $lastPage ? $currentPage + 1 : null;

        return new Paginator(
            currentPage: $currentPage,
            itemsPerPage: $this->itemsPerPage,
            totalItems: $this->totalItems,
            lastPage: $lastPage,
            previousPage: $previousPage,
            nextPage: $nextPage,
            firstItemNumber: $firstItemNumber,
            lastItemNumber: $lastItemNumber,
            hasNextPage: $currentPage < $lastPage,
            hasPreviousPage: $currentPage > 1,
            urlGenerator: $this->makeUrlGenerator(),
        );
    }

    public function buildConfig(): PaginatorConfig
    {
        return new PaginatorConfig(
            currentPage: $this->currentPage,
            itemsPerPage: $this->itemsPerPage,
        );
    }

    public function buildEmpty(): Paginator
    {
        return new Paginator(
            currentPage: 1,
            itemsPerPage: 10,
            totalItems: 0,
            lastPage: 0,
            previousPage: null,
            nextPage: null,
            firstItemNumber: 0,
            lastItemNumber: 0,
            hasNextPage: false,
            hasPreviousPage: false,
            urlGenerator: $this->makeUrlGenerator(),
        );
    }
}