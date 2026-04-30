<?php

namespace HeimrichHannot\FlareBundle\Filter;

interface FilterBuilderInterface
{
    public function add(string $type, array $options): static;
}