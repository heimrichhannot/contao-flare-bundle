<?php

namespace HeimrichHannot\FlareBundle\Paginator\Provider;

use HeimrichHannot\FlareBundle\Paginator\Paginator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

readonly class PaginatorUrlProvider
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    /**
     * @return callable(int $page): string
     */
    public function createGenerator(
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
     * Creates a generator from the given request.
     * @return callable(int $page): string
     */
    public function createGeneratorFromRequest(
        Request $request,
        string $pageParam,
        ?string $routeName = null,
        ?array  $routeParams = null,
    ): callable {
        if (!$routeName ??= $request->attributes->get('_route')) {
            throw new \RuntimeException('No route found in current request');
        }

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

        return $this->createGenerator($routeName, $pageParam, $routeParams);
    }
}