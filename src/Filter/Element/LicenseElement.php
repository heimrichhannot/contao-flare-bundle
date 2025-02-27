<?php

namespace HeimrichHannot\FlareBundle\Filter\Element;

use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Form\Type\LicenseFilterType;

#[AsFilterElement(
    alias: LicenseElement::TYPE,
    formType: LicenseFilterType::class
)]
class LicenseElement extends AbstractFilterElement
{
    const TYPE = 'flare_license';

    public function __invoke()
    {
        // TODO: Implement __invoke() method.
    }

    public function formTypeOptions(): array
    {
        return [];
    }
}