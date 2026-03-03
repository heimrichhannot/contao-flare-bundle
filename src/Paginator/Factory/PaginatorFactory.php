<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Paginator\Factory;

use HeimrichHannot\FlareBundle\Paginator\Paginator;
use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

readonly class PaginatorFactory
{
    public const DEFAULT_PAGE_PARAM = 'page';

    public function __construct(
        private RequestStack          $requestStack,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function create(PaginatorConfig $config, ?string $pageParam = null, ?Request $request = null): Paginator
    {
        $request ??= $this->requestStack->getCurrentRequest();
        if (!$request) {
            throw new \RuntimeException('No request found in current request');
        }

        $pageParam = $this->sanitizePageParam($pageParam);

        $urlGenerator = $this->createUrlGeneratorFromRequest(
            request: $request,
            pageParam: $pageParam
        );

        return new Paginator(
            itemsPerPage: $config->getItemsPerPage(),
            currentPage: $request->query->getInt($pageParam, 1),
            totalItems: $config->getTotalItems(),
            urlGenerator: $urlGenerator,
        );
    }

    public function createEmpty(?string $pageParam = null, ?Request $request = null): Paginator
    {
        return new Paginator(
            itemsPerPage: 10,
            currentPage: 1,
            totalItems: 0,
            urlGenerator: $this->createUrlGeneratorFromRequest($pageParam, $request),
        );
    }

    public function createUrlGeneratorFromRequest(
        ?Request $request = null,
        ?string  $pageParam = null,
        ?string  $routeName = null,
        ?array   $routeParams = null,
    ): callable {
        $request ??= $this->requestStack->getCurrentRequest();
        if (!$request) {
            throw new \RuntimeException('No request found in current request');
        }

        if (!$routeName ??= $request->attributes->get('_route')) {
            throw new \RuntimeException('No route found in current request');
        }

        $pageParam = $this->sanitizePageParam($pageParam);

        if (\is_null($routeParams))
        {
            $params = $request->attributes->get('_route_params', []);

            // Merge query parameters, excluding the page parameter
            $queryParams = \array_filter(
                $request->query->all(),
                static fn (string $key): bool => $key !== $pageParam,
                \ARRAY_FILTER_USE_KEY,
            );

            $routeParams = \array_merge($params, $queryParams);
        }

        return $this->createUrlGenerator($routeName, $pageParam, $routeParams);
    }


    /**
     * @return callable(int $page): string
     */
    public function createUrlGenerator(
        string $routeName,
        string $pageParam,
        ?array  $routeParams = null,
    ): callable {
        $routeParams ??= [];
        return fn (int $page): string => $this->urlGenerator->generate(
            $routeName,
            \array_merge($routeParams, [$pageParam => $page]),
        );
    }

    /**
     * Checks if the given page parameter name is valid.
     *
     * @param string $param The page parameter name to check.
     * @return bool Whether the parameter name is valid or not.
     */
    public function isPageParamValid(string $param): bool
    {
        return \preg_match('/^[a-z0-9_]+$/i', $param) === 1;
    }

    /**
     * Helper function to validate and sanitize a page parameter name.
     *
     * If the prefix is null, the default page parameter name 'page' is returned.
     * Otherwise, the prefix is sanitized.
     *
     * @param ?string $param The page parameter name to sanitize.
     * @return string The sanitized page parameter name.
     */
    public function sanitizePageParam(?string $param): string
    {
        if (!$param) {
            return self::DEFAULT_PAGE_PARAM;
        }

        if (!$this->isPageParamValid($param)) {
            $param = (string) \preg_replace(['/[^a-z0-9_]/i', '/_{2,}/'], ['_', '_'], $param);
        }

        return $param ?: self::DEFAULT_PAGE_PARAM;
    }
}