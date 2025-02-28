<?php

namespace HeimrichHannot\FlareBundle\List\Type;

use Contao\DataContainer;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class AbstractListType implements TranslatorInterface
{
    public function __construct(
        private readonly TranslatorInterface $translator
    ) {}

    abstract public function getBaseDataContainerName(array $row, DataContainer $dc): string;

    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        return $this->translator->trans('list_type.' . $id, [], 'flare', $locale);
    }
}