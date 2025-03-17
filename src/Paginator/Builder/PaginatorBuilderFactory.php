<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Paginator\Builder;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

readonly class PaginatorBuilderFactory
{
    public function __construct(
        private RequestStack          $requestStack,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function create(): PaginatorBuilder
    {
        return new PaginatorBuilder(
            requestStack: $this->requestStack,
            urlGenerator: $this->urlGenerator,
        );
    }
}