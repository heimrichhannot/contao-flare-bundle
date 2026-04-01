<?php

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
        $stopWords = $this->parameterBag->get("huh_flare.search_stop_words.{$locale}");

        if (!$stopWords && \str_contains($locale, '_')) {
            $locale = \strtok($locale, '_');
            $stopWords = $this->parameterBag->get("huh_flare.search_stop_words.{$locale}");
        }

        return $stopWords ?? [];
    }
}