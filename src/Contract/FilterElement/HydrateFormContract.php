<?php

namespace HeimrichHannot\FlareBundle\Contract\FilterElement;

use HeimrichHannot\FlareBundle\Filter\FilterContext;
use Symfony\Component\Form\FormInterface;

interface HydrateFormContract
{
    public function hydrateForm(FilterContext $context, FormInterface $field): void;
}