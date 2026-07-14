<?php

namespace HeimrichHannot\FlareBundle\Config;

interface TransformerInterface
{
    public function __invoke(ConfigBuilder $config, object $source): void;
}
