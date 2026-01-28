<?php

namespace HeimrichHannot\FlareBundle\Contract\FilterElement;

use HeimrichHannot\FlareBundle\Filter\FilterDefinition;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use Symfony\Component\Form\FormInterface;

interface HydrateFormContract
{
    public function hydrateForm(FormInterface $field, ListSpecification $list, FilterDefinition $filter): void;
}