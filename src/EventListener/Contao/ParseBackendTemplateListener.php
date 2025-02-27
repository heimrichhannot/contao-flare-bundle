<?php

namespace HeimrichHannot\FlareBundle\EventListener\Contao;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use HeimrichHannot\FlareBundle\FilterElement\FilterElementRegistry;

#[AsHook('parseBackendTemplate')]
readonly class ParseBackendTemplateListener
{
    public function __construct(
        private FilterElementRegistry $filterElementRegistry,
    ) {}

    public function __invoke(string $buffer, string $template): string
    {
        if ('be_main' === $template) {
            $elements = $this->filterElementRegistry->all();
            \dump('parseBackendTemplate: all filter elements', $elements);
        }

        return $buffer;
    }
}