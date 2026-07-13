<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\FilterElement;

use HeimrichHannot\FlareBundle\Contract\IsSupportedContract;
use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use Symfony\Component\Form\FormBuilderInterface;

abstract class AbstractFilterElement implements FilterElementInterface, IsSupportedContract
{
    public function buildForm(FormBuilderInterface $builder, FilterContext $context): void {}

    public function buildFilter(FilterBuilderInterface $builder, FilterContext $context, array $data): void {}

    public function isSupported(): bool
    {
        return true;
    }
}
