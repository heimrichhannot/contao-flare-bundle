<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Form;

use HeimrichHannot\FlareBundle\FilterElement\FilterElementContext;
use Symfony\Component\Form\FormBuilderInterface;

interface FilterFormBuilderInterface
{
    public function add(FilterElementContext $context, ?string $formType = null, array $options = []): static;

    public function getRootBuilder(): FormBuilderInterface;
}
