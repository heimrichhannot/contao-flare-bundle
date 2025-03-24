<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Twig;

use Contao\StringUtil;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Twig\TwigFilter;

class Format extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('fmt_headline', $this->fmtHeadline(...), ['is_safe' => ['html']]),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('fmt_headline', $this->fmtHeadline(...), ['is_safe' => ['html']]),
            new TwigFilter('fmt_headline_value', $this->fmtHeadlineValue(...)),
        ];
    }

    public function fmtHeadline(string|array|null $value): string
    {
        if (empty($value)) {
            return '';
        }

        if (\is_string($value) && \str_starts_with($value, 'a:') && \str_contains($value, '{'))
        {
            $deserialized = StringUtil::deserialize($value);

            if (!\is_array($deserialized)) {
                return $value;
            }

            $value = $deserialized;
        }

        if (\is_array($value))
        {
            $unit = $deserialized['unit'] ?? 'h2';
            $value = $deserialized['value'] ?? '';

            return "<$unit>$value</$unit>";
        }

        return $value;
    }

    public function fmtHeadlineValue(?string $value): string
    {
        if (empty($value)) {
            return '';
        }

        if (\str_starts_with($value, 'a:') && \str_contains($value, '{'))
        {
            $deserialized = StringUtil::deserialize($value);

            if (!\is_array($deserialized)) {
                return $value;
            }

            return $deserialized['value'] ?? '';
        }

        return $value;
    }
}
