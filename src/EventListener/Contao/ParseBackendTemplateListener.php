<?php

namespace HeimrichHannot\FlareBundle\EventListener\Contao;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use HeimrichHannot\FlareBundle\Filter\FilterElementRegistry;
use HeimrichHannot\FlareBundle\List\ListTypeRegistry;

#[AsHook('parseBackendTemplate')]
readonly class ParseBackendTemplateListener
{
    public function __construct(
        private FilterElementRegistry $filterElementRegistry,
        private ListTypeRegistry      $listTypeRegistry,
    ) {}

    public function __invoke(string $buffer, string $template): string
    {
        if ('be_main' === $template)
        {
            // $elements = $this->filterElementRegistry->all();
            // \dump('parseBackendTemplate: all filter elements', $elements);

            // $types = $this->listTypeRegistry->all();
            // \dump('parseBackendTemplate: all list types', $types);
        }

        return $buffer;
    }
}