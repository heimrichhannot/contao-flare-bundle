<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\EventListener\Contao;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\Widget;
use HeimrichHannot\FlareBundle\Util\Str;

/**
 * The `flare_form_name` rgxp: a filter alias names the filter's form field, so it must be a valid Symfony form name.
 * Contao's own `alias` rgxp also accepts dots, leading hyphens and non-ASCII letters.
 */
#[AsHook('addCustomRegexp')]
final readonly class FormNameRegexpListener
{
    public const RGXP = 'flare_form_name';

    public function __invoke(string $regexp, mixed $input, Widget $widget): bool
    {
        if ($regexp !== self::RGXP) {
            return false;
        }

        if (!Str::isValidFormName((string) $input)) {
            $widget->addError($GLOBALS['TL_LANG']['ERR']['flare']['form_name']);
        }

        return true;
    }
}
