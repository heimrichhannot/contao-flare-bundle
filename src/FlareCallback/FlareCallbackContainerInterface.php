<?php

namespace HeimrichHannot\FlareBundle\FlareCallback;

use Contao\DataContainer;

/**
 * @internal For internal use only. API might change without notice.
 */
interface FlareCallbackContainerInterface
{
    public function handleFieldOptions(?DataContainer $dc): array;

    public function handleLoadField(mixed $value, DataContainer $dc): mixed;

    public function handleSaveField(mixed $value, DataContainer $dc): mixed;
}