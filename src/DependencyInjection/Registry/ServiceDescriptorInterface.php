<?php

namespace HeimrichHannot\FlareBundle\DependencyInjection\Registry;

interface ServiceDescriptorInterface
{
    public function getAttributes(): array;

    public function getMethod(): ?string;

    public function getService(): object;
}