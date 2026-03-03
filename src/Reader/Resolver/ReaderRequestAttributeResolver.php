<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Reader\Resolver;

use HeimrichHannot\FlareBundle\Reader\Factory\ReaderRequestAttributeFactory;
use HeimrichHannot\FlareBundle\Reader\ReaderRequestAttribute;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class ReaderRequestAttributeResolver
{
    public const ATTRIBUTE_KEY = 'flare_reader';

    public function __construct(
        private ReaderRequestAttributeFactory $factory,
        private RequestStack                  $requestStack,
    ) {}

    public function store(ReaderRequestAttribute $attribute, ?Request $request = null): void
    {
        $request ??= $this->requestStack->getCurrentRequest();
        $request?->attributes->set(self::ATTRIBUTE_KEY, $attribute->marshall());
    }

    public function resolve(?Request $request = null): ?ReaderRequestAttribute
    {
        $request ??= $this->requestStack->getCurrentRequest();
        $data = $request?->attributes->get(self::ATTRIBUTE_KEY) ?? [];
        return $this->factory->createFromData($data);
    }
}