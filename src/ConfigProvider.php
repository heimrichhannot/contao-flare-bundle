<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly final class ConfigProvider
{
    public function __construct(
        private ParameterBagInterface $parameterBag,
        private TranslatorInterface $translator,
    ) {}

    public function getStopWords(?string $locale = null): array
    {
        $locale ??= $this->translator->getLocale();
        $stopWords = $this->tryGetStopWords($locale);

        if (!$stopWords && \str_contains($locale, '_')) {
            $locale = \strtok($locale, '_');
            $stopWords = $this->tryGetStopWords($locale);
        }

        return $stopWords ?? [];
    }

    private function tryGetStopWords(string $locale): ?array
    {
        $paramName = "huh_flare.search_stop_words.{$locale}";

        return $this->parameterBag->has($paramName)
            ? $this->parameterBag->get($paramName)
            : null;
    }
}