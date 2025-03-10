<?php

namespace HeimrichHannot\FlareBundle\DependencyInjection\Registry;

interface ServiceConfigInterface
{
    public function getAttributes(): array;

    public function getMethod(): ?string;

    public function getService(): object;
}