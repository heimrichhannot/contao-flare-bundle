<?php

namespace HeimrichHannot\FlareBundle\Manager;

use Contao\Controller;
use HeimrichHannot\FlareBundle\Model\ListModel;

readonly class TranslationManager
{
    public function __construct(
        // private readonly TranslatorInterface $translator,
    ) {}

    public function listModel(ListModel|string $listModel_or_alias): string
    {
        Controller::loadLanguageFile('default');

        $alias = (string) ($listModel_or_alias instanceof ListModel ? $listModel_or_alias->type : $listModel_or_alias);

        $lang = $GLOBALS['TL_LANG']['FLARE']['list'][$alias] ?? null;

        /** @noinspection PhpDuplicateMatchArmBodyInspection */
        return match(true) {
            !$lang => $alias,
            \is_array($lang) => (string) ($lang[0] ?? $alias),
            \is_string($lang) => $lang,
            default => $alias,
        };
    }

    public function filterElement(string $alias): string
    {
        Controller::loadLanguageFile('default');

        $lang = $GLOBALS['TL_LANG']['FLARE']['filter'][$alias] ?? null;

        /** @noinspection PhpDuplicateMatchArmBodyInspection */
        return match(true) {
            !$lang => $alias,
            \is_array($lang) => (string) ($lang[0] ?? $alias),
            \is_string($lang) => $lang,
            default => $alias,
        };
    }
}