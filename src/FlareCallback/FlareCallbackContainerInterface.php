<?php

namespace HeimrichHannot\FlareBundle\FlareCallback;

use Contao\DataContainer;

/**
 * @internal For internal use only. API might change without notice.
 */
interface FlareCallbackContainerInterface
{
    public function getFieldOptions(?DataContainer $dc): array;

    public function onLoadField(mixed $value, DataContainer $dc): mixed;

    public function onSaveField(mixed $value, DataContainer $dc): mixed;
}