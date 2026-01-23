<?php

namespace HeimrichHannot\FlareBundle\Contract\FilterElement;

use HeimrichHannot\FlareBundle\Filter\FilterDefinition;
use HeimrichHannot\FlareBundle\List\ListDefinition;
use Symfony\Component\Form\FormInterface;

interface HydrateFormContract
{
    public function hydrateForm(FormInterface $field, ListDefinition $list, FilterDefinition $filter): void;
}