<?php

namespace HeimrichHannot\FlareBundle\Manager;

use Contao\Controller;
use HeimrichHannot\FlareBundle\Model\ListModel;

readonly class TranslationManager
{
    public function __construct(
        // private readonly TranslatorInterface $translator,
    ) {}

    public function listModelType(ListModel|string $listModel_or_type): string
    {
        Controller::loadLanguageFile('default');

        $type = $listModel_or_type;
        if ($listModel_or_type instanceof ListModel) {
            $type = $listModel_or_type->type;
        }

        $type = (string) $type;

        $lang = $GLOBALS['TL_LANG']['FLARE']['list'][$type] ?? null;

        /** @noinspection PhpDuplicateMatchArmBodyInspection */
        return match(true) {
            empty($lang) => $type,
            \is_array($lang) => (string) ($lang[0] ?? $type),
            \is_string($lang) => $lang,
            default => $type,
        };
    }
}