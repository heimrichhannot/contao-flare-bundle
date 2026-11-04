<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Intent;

interface FormIntentInterface
{
    public function getFormName(): string;

    public function createFormActionUrl(): ?string;
}
