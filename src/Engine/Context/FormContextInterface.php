<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Context;

interface FormContextInterface
{
    public function getFormName(): string;

    public function createFormActionUrl(): ?string;
}
