<?php

namespace HeimrichHannot\FlareBundle\Form;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class ChoicesBuilderFactory
{
    public function __construct(
        private TranslatorInterface $translator,
        private ParameterBagInterface $parameterBag,
    ) {}

    public function createChoicesBuilder(): ChoicesBuilder
    {
        return new ChoicesBuilder($this->translator, $this->parameterBag);
    }
}