<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contao\Reader;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Input;

/**
 * Reads the auto-item of the current request the way the reader reads it: input-encoded, and without marking it as
 * used, so that only the reader's own `Input::get('auto_item')` claims it.
 */
final readonly class AutoItemReader
{
    public function __construct(
        private ContaoFramework $framework,
    ) {}

    /**
     * The auto-item, or null when there is none (missing, empty or not a string).
     */
    public function read(): ?string
    {
        $value = $this->framework->getAdapter(Input::class)->get('auto_item', false, true);

        return \is_string($value) && $value !== '' ? $value : null;
    }
}
