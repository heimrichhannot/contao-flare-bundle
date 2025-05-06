<?php

namespace HeimrichHannot\FlareBundle\Paginator\Builder;

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
        ?string $routeName = null,
        ?array  $routeParams = null,
        ?string $queryPrefix = null,
    ): callable {
        return function (int $page) use ($routeName, $routeParams, $queryPrefix) {
            return $this->urlGenerator->generate(
                $routeName,
                \array_merge($routeParams, [Paginator::pageParam($queryPrefix) => $page]),
            );
        };
    }

    /**
     * Creates a generator from the given request.
     * @return callable(int $page): string
     */
    public function createGeneratorFromRequest(
        Request $request,
        ?string $routeName = null,
        ?array  $routeParams = null,
        ?string $queryPrefix = null,
    ): callable {
        if (!$routeName ??= $request->attributes->get('_route')) {
            throw new \RuntimeException('No route found in current request');
        }

        if (\is_null($routeParams))
        {
            $params = $request->attributes->get('_route_params', []);
            $pageParam = self::pageParam($queryPrefix);

            // Merge query parameters, excluding the page parameter
            $queryParams = \array_filter(
                $request->query->all(),
                static fn(string $key) => $key !== $pageParam,
                \ARRAY_FILTER_USE_KEY,
            );

            $routeParams = \array_merge($params, $queryParams);
        }

        return $this->createGenerator($routeName, $routeParams, $queryPrefix);
    }

    public static function pageParam(string $prefix = null): string
    {
        $prefix = \preg_replace(['/[^a-z0-9_]/i', '/_?page$/i', '/_{2,}/'], ['_', '', '_'], $prefix);
        return $prefix ? \rtrim($prefix, '_') . '_page' : 'page';
    }
}