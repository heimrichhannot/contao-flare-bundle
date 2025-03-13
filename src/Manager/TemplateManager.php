<?php

namespace HeimrichHannot\FlareBundle\Manager;

use Contao\CoreBundle\Twig\Finder\Finder;
use Contao\CoreBundle\Twig\Finder\FinderFactory;

readonly class TemplateManager
{
    public function __construct(private FinderFactory $finderFactory) {}

    public function getTemplateFinder(string $namespace, ?string $extension = null): Finder
    {
        return $this->finderFactory
            ->create()
            ->identifier($namespace)
            ->extension($extension ?? 'html.twig')
            ->withVariants();
    }
}