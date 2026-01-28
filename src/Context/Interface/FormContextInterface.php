<?php

namespace HeimrichHannot\FlareBundle\Context\Interface;

interface FormContextInterface
{
    public function getFormName(): string;

    public function getFormActionPage(): int;
}