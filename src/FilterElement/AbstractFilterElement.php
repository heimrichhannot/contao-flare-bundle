<?php

namespace HeimrichHannot\FlareBundle\FilterElement;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormTypeInterface;

abstract class AbstractFilterElement
{
    /**
     * @return class-string<FormTypeInterface>|null
     */
    public function getFormType(): ?string
    {
        return null;
    }

    public function hasFormType(): bool
    {
        $class = $this->getFormType();
        return $class !== null && \class_exists($class);
    }

    public function modifyForm(FormBuilderInterface $builder, array $options): void {}
}