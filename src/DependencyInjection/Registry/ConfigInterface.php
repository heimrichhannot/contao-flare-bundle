<?php

namespace HeimrichHannot\FlareBundle\DependencyInjection\Registry;

interface ConfigInterface
{
    public function getService(): object;

    public function getAttributes(): array;

    public function getPalette(): ?string;
}