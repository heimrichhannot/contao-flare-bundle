<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\InferPtable\Factory;

use HeimrichHannot\FlareBundle\InferPtable\PtableInferrable;

class PtableInferrableFactory
{
    /**
     * Creates an inferrable from a list's canonical config
     * ({@see \HeimrichHannot\FlareBundle\List\ListSpec::$config}).
     *
     * @param array<string, mixed> $config
     */
    public static function createFromConfig(array $config): PtableInferrable
    {
        return new PtableInferrable(
            fieldPid: (string) ($config['fieldPid'] ?? ''),
            whichPtable: (string) ($config['whichPtable'] ?? ''),
            fieldPtable: (string) ($config['fieldPtable'] ?? ''),
            tablePtable: (string) ($config['tablePtable'] ?? ''),
        );
    }
}