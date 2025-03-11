<?php

namespace HeimrichHannot\FlareBundle\Contract;

interface LabelableInterface
{
    /**
     * @return array<string, scalar>
     */
    public function getLabelParameters(): array;
}