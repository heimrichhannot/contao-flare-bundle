<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contract\FilterElement;

use Symfony\Component\Form\FormInterface;

interface FormDataContract
{
    public function extractFormData(FormInterface $form): mixed;
}