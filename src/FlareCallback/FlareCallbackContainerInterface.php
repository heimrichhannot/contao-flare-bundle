<?php

namespace HeimrichHannot\FlareBundle\FlareCallback;

use Contao\DataContainer;

/**
 * @internal For internal use only. API might change without notice.
 */
interface FlareCallbackContainerInterface
{
    public function handleFieldOptions(?DataContainer $dc, string $target): array;

    public function handleLoadField(mixed $value, ?DataContainer $dc, string $target): mixed;

    public function handleSaveField(mixed $value, ?DataContainer $dc, string $target): mixed;
}