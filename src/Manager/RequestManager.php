<?php

namespace HeimrichHannot\FlareBundle\Manager;

use HeimrichHannot\FlareBundle\Dto\ReaderRequestAttribute;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class RequestManager
{
    public function __construct(
        private RequestStack $requestStack,
    ) {}

    public function setReader(ReaderRequestAttribute $attribute): void
    {
        $this->requestStack->getMainRequest()?->attributes->set('flare_reader', $attribute->marshall());
    }

    public function getReader(): ?ReaderRequestAttribute
    {
        $data = $this->requestStack->getMainRequest()?->attributes->get('flare_reader') ?? [];
        return ReaderRequestAttribute::unmarshall($data);
    }
}