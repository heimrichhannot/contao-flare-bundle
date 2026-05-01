<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contract\FilterElement;

use HeimrichHannot\FlareBundle\Specification\ConfiguredFilter;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use Symfony\Component\Form\FormInterface;

interface HydrateFormContract
{
    public function hydrateForm(FormInterface $field, ListSpecification $list, ConfiguredFilter $filter): void;
}