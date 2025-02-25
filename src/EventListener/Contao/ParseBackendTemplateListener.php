<?php

namespace HeimrichHannot\FlareBundle\EventListener\Contao;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use HeimrichHannot\FlareBundle\Manager\FilterElementManager;

#[AsHook('parseBackendTemplate')]
readonly class ParseBackendTemplateListener
{
    public function __construct(
        private FilterElementManager $filterElementManager,
    ) {}

    public function __invoke(string $buffer, string $template): string
    {
        if ('be_main' === $template) {
            $elements = $this->filterElementManager->getFilterElements();
            \dump($elements);
        }

        return $buffer;
    }
}