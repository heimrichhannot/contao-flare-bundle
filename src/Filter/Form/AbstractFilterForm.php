<?php

namespace HeimrichHannot\FlareBundle\Filter\Form;

use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use Symfony\Component\Form\FormInterface;

/**
 * @template T of ValueInterface
 * @api
 */
abstract class AbstractFilterForm implements FilterFormInterface
{
    /**
     * {@inheritdoc}
     *
     * @return class-string<T>
     */
    abstract public function getValueClass(): string;

    abstract public function buildForm(FilterFormBuilderInterface $builder, FilterContext $context): void;

    /**
     * {@inheritdoc}
     *
     * @return T|null A value object of the class this form is registered for.
     *  Null contributes nothing, which lets the element fall back to its own config.
     */
    abstract public function decode(FormInterface $form, FilterContext $context): ?ValueInterface;
}
