<?php

namespace HeimrichHannot\FlareBundle\Engine\Context\Interface;

interface FormContextInterface
{
    public function getFormName(): string;

    public function getFormActionPage(): int;
}