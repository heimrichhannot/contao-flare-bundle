<?php

namespace HeimrichHannot\FlareBundle\FilterElement;

use Symfony\Contracts\Translation\TranslatorInterface;

abstract class AbstractFilterElement implements TranslatorInterface
{
    public function __construct(
        private readonly TranslatorInterface $translator
    ) {}

    public function __invoke()
    {
        // TODO: Implement __invoke() method.
    }

    public function formTypeOptions(): array
    {
        return [];
    }

    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        return $this->translator->trans('filter_element.' . $id, $parameters, $domain ?? 'flare', $locale);
    }
}