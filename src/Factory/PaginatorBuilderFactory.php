<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Factory;

use HeimrichHannot\FlareBundle\Paginator\PaginatorBuilder;
use HeimrichHannot\FlareBundle\Paginator\Provider\PaginatorUrlProvider;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class PaginatorBuilderFactory
{
    public function __construct(
        private PaginatorUrlProvider $urlProvider,
        private RequestStack         $requestStack,
    ) {}

    public function create(): PaginatorBuilder
    {
        return new PaginatorBuilder(
            urlProvider: $this->urlProvider,
            requestStack: $this->requestStack,
        );
    }
}