<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\FilterElement;

use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterInvocation;
use HeimrichHannot\FlareBundle\Form\FilterFormBuilderInterface;

interface FilterElementInterface
{
    public function buildForm(FilterFormBuilderInterface $builder, FilterElementContext $context): void;

    public function buildFilter(FilterBuilderInterface $builder, FilterInvocation $invocation): void;
}