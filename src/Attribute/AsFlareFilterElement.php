<?php

namespace HeimrichHannot\FlareBundle\Attribute;

use Attribute;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Attribute(Attribute::TARGET_CLASS)]
class AsFlareFilterElement implements TranslatableInterface
{
    public function __construct(
        public string $alias,
    ) {}

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans($this->alias, [], 'flare', $locale);
    }
}