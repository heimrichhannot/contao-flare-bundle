<?php

namespace HeimrichHannot\FlareBundle\DependencyInjection\Registry;

interface ServiceConfigInterface
{
    public function getService(): object;

    public function getAttributes(): array;
}