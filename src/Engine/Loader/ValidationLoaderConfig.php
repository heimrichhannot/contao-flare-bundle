<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Loader;

use HeimrichHannot\FlareBundle\Engine\Context\ValidationContext;
use HeimrichHannot\FlareBundle\Lists\ListSpec;

readonly class ValidationLoaderConfig
{
    public function __construct(
        public ListSpec $list,
        public ValidationContext $context,
        public string            $autoItemField,
    ) {}
}