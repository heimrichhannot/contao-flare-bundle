<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Twig;

use HeimrichHannot\FlareBundle\Util\Str;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Twig\TwigFilter;

class Format extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('flare_fmt_headline', $this->fmtHeadline(...), ['is_safe' => ['html']]),
            new TwigFunction('flare_fmt_headline_value', $this->fmtHeadlineValue(...)),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('flare_fmt_headline', $this->fmtHeadline(...), ['is_safe' => ['html']]),
            new TwigFilter('flare_fmt_headline_value', $this->fmtHeadlineValue(...)),
        ];
    }

    public function fmtHeadline(string|array|null $value): string
    {
        return Str::getHeadline($value, withTags: true);
    }

    public function fmtHeadlineValue(string|array|null $value): string
    {
        return Str::getHeadline($value);
    }
}