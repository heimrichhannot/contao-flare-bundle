<?php

namespace HeimrichHannot\FlareBundle\DataContainer\Builder;

interface DcaFieldBuilderInterface
{
    public function inputType(string $inputType): self;

    public function eval(array $eval): self;

    public function merge(array $definition): self;

    public function options(callable|array $options): self;

    public function load(callable $fn): self;

    public function save(callable $fn): self;

    public function applyTo(array &$definition): void;
}
