<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Context\Interface;

interface FormContextInterface
{
    public function getFormName(): string;

    public function getFormActionPage(): int;
}