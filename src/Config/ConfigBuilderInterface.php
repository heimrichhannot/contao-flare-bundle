<?php

namespace HeimrichHannot\FlareBundle\Config;

interface ConfigBuilderInterface
{
    public function set(string $key, mixed $value): self;

    public function get(string $key): mixed;

    public function all(): iterable;
}
