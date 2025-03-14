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
            new TwigFunction('flare', [FlareRuntime::class, 'getFlare']),
            new TwigFunction('flare_form', [FlareRuntime::class, 'createFormView']),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('flare_form', [FlareRuntime::class, 'createFormView']),
        ];
    }
}
