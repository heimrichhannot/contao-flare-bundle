<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Paginator;

use HeimrichHannot\FlareBundle\Paginator\Provider\PaginatorUrlProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class PaginatorBuilder
{
    private ?int $currentPage = null;
    private ?int $itemsPerPage = null;
    private ?int $totalItems = null;
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
        $this->currentPage = $config->getCurrentPageNumber();
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
        $itemsPerPage = $this->itemsPerPage ?? 0;
        $totalItems = $this->totalItems ?? -1;

        $lastPage = ($itemsPerPage > 0 && $totalItems > -1)
            ? (int) \ceil($totalItems / $itemsPerPage)
            : null;

        $currentPage = $this->currentPage;

        if (!\is_null($this->currentPage) && !\is_null($lastPage)) {
            $currentPage = (int) \max(1, \min($lastPage, $this->currentPage));
        }

        return new Paginator(
            itemsPerPage: $itemsPerPage,
            currentPage: $currentPage,
            totalItems: $totalItems,
            urlGenerator: $this->makeUrlGenerator(),
        );
    }

    public function buildConfig(): PaginatorConfig
    {
        return new PaginatorConfig(
            itemsPerPage: $this->itemsPerPage,
            currentPage: $this->currentPage,
            totalItems: $this->totalItems,
        );
    }

    public function buildEmpty(): Paginator
    {
        return new Paginator(
            itemsPerPage: 10,
            currentPage: 1,
            totalItems: 0,
            urlGenerator: $this->makeUrlGenerator(),
        );
    }
}