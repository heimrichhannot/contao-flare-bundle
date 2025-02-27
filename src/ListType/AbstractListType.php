<?php

namespace HeimrichHannot\FlareBundle\ListType;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class AbstractListType
{
    public function trans(TranslatorInterface $translator, string $alias, ?string $locale = null): string
    {
        return $translator->trans('list_type.' . '<alias>', [], 'flare', $locale);
    }
}