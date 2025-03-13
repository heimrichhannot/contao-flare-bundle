<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Twig\Extension;

use HeimrichHannot\FlareBundle\Twig\Runtime\FlareRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class FlareExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('flare_form', [FlareRuntime::class, 'getFormView']),
            new TwigFunction('flare_form_component', [FlareRuntime::class, 'getFormComponent']),
            new TwigFunction('flare_list', [FlareRuntime::class, 'getEntries']),
        ];
    }

    public function getFilters(): array
    {
        return [];
    }
}
