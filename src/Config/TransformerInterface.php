<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Config;

interface TransformerInterface
{
    public function __invoke(ConfigBuilder $config, object $source): void;
}
