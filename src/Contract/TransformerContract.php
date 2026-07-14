<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contract;

use HeimrichHannot\FlareBundle\Config\TransformerBuilder;

/**
 * Implemented by filter elements and list types that own the translation from stored
 * sources (e.g. a DCA model) into their canonical config values.
 *
 * Like {@see OptionsContract::configureOptions()}, this is declarative, memoizable setup —
 * the configured transformers are cached per class and run by the framework whenever a
 * source needs translating.
 */
interface TransformerContract
{
    /**
     * Declares per-source transformers translating a stored source into canonical config values.
     */
    public function configureTransformers(TransformerBuilder $transformers): void;
}
