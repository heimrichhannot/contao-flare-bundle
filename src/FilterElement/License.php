<?php

namespace HeimrichHannot\FlareBundle\FilterElement;

use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Form\Type\LicenseFilterType;

#[AsFilterElement(
    alias: License::ALIAS,
    formType: LicenseFilterType::class
)]
class License extends AbstractFilterElement
{
    const ALIAS = 'flare_license';

    public function __invoke()
    {
        // TODO: Implement __invoke() method.
    }

    public function formTypeOptions(): array
    {
        return [];
    }
}