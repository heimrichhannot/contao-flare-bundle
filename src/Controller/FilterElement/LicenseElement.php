<?php

namespace HeimrichHannot\FlareBundle\Controller\FilterElement;

use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\FormType\LicenseFilterType;

#[AsFilterElement(
    alias: LicenseElement::TYPE,
    formType: LicenseFilterType::class
)]
class LicenseElement extends AbstractFilterElementController
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