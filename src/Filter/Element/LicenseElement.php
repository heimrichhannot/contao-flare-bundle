<?php

namespace HeimrichHannot\FlareBundle\Filter\Element;

use Doctrine\DBAL\Query\QueryBuilder;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Form\Type\LicenseFilterType;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;

#[AsFilterElement(
    alias: LicenseElement::TYPE,
    formType: LicenseFilterType::class
)]
class LicenseElement extends AbstractFilterElement
{
    const TYPE = 'flare_license';

    public function formTypeOptions(): array
    {
        return [];
    }
}