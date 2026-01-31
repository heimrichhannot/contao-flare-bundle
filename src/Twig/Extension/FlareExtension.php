<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Twig\Extension;

use HeimrichHannot\FlareBundle\Twig\Runtime\FlareRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class FlareExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('flare_content', [FlareRuntime::class, 'getTlContent'], ['is_safe' => ['html']]),
            new TwigFunction('flare_enclosure', [FlareRuntime::class, 'getEnclosure']),
            new TwigFunction('flare_enclosure_files', [FlareRuntime::class, 'getEnclosureFiles']),
            new TwigFunction('flare_make_filter', [FlareRuntime::class, 'makeFilter']),
            new TwigFunction('flare_project', [FlareRuntime::class, 'project']),
            new TwigFunction('flare_copy_view', [FlareRuntime::class, 'copyView']),
            new TwigFunction('flare_schema_org', [FlareRuntime::class, 'getSchemaOrg'], ['needs_context'=> true]),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('flare_form', [FlareRuntime::class, 'createFormView']),
        ];
    }
}
