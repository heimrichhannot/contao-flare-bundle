<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Loader;

use HeimrichHannot\FlareBundle\Engine\Intent\ValidationIntent;
use HeimrichHannot\FlareBundle\List\ListSpec;

readonly class ValidationLoaderConfig
{
    public function __construct(
        public ListSpec         $list,
        public ValidationIntent $intent,
        public string           $autoItemField,
    ) {}
}
