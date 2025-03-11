<?php

namespace HeimrichHannot\FlareBundle\Form;

use Symfony\Contracts\Translation\TranslatorInterface;

readonly class ChoicesBuilderFactory
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {}

    public function createChoicesBuilder(): ChoicesBuilder
    {
        return new ChoicesBuilder($this->translator);
    }
}