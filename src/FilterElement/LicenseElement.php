<?php

namespace HeimrichHannot\FlareBundle\FilterElement;

use HeimrichHannot\FlareBundle\Attribute\AsFlareFilterElement;
use HeimrichHannot\FlareBundle\FormType\LicenseFilterType;

#[AsFlareFilterElement(
    alias: LicenseElement::TYPE,
    formType: LicenseFilterType::class
)]
class LicenseElement
{
    const TYPE = 'flare_license';

    public function __invoke()
    {
        // TODO: Implement __invoke() method.
    }
}