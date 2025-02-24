<?php

/*
 * @copyright Copyright (c) 2025, Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FlareBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class HeimrichHannotFlareBundle extends Bundle
{
    /**
     * @{inheritdoc}
     */
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}