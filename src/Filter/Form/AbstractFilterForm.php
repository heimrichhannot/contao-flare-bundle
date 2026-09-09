<?php

namespace HeimrichHannot\FlareBundle\Filter\Form;

use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use Symfony\Component\Form\FormInterface;

abstract class AbstractFilterForm implements FilterFormInterface
{
    abstract public function getValueClass(): string;

    abstract public function buildForm(FilterFormBuilderInterface $builder, FilterContext $context): void;

    abstract public function decode(FormInterface $form, FilterContext $context): ?ValueInterface;
}
