<?php

namespace HeimrichHannot\FlareBundle\Engine\Loader;

use HeimrichHannot\FlareBundle\Engine\Context\ValidationContext;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

readonly class ValidationLoaderConfig
{
    public function __construct(
        public ListSpecification $list,
        public ValidationContext $context,
        public string            $autoItemField,
    ) {}
}