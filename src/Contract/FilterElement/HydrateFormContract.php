<?php

namespace HeimrichHannot\FlareBundle\Contract\FilterElement;

use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterDefinition;
use HeimrichHannot\FlareBundle\List\ListDefinition;
use Symfony\Component\Form\FormInterface;

interface HydrateFormContract
{
    public function hydrateForm(ListDefinition $list, FilterDefinition $filter, FormInterface $field): void;
}