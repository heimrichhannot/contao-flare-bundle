<?php

namespace HeimrichHannot\FlareBundle\Contract;

/**
 * @experimental Do not use this interface, as it has not been tested yet.
 */
interface LabelableInterface
{
    /**
     * @return array<string, scalar>
     */
    public function getLabelParameters(): array;
}