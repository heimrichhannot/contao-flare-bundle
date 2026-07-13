<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Element;

use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Implemented by filter elements that declare a canonical configuration schema.
 *
 * The element owns both the schema and the translation from the stored DCA row into
 * canonical config values, so its runtime methods never touch storage column names.
 */
interface FilterElementOptionsInterface
{
    /**
     * Declares the canonical config schema, mirroring how filter types configure their options.
     */
    public function configureOptions(OptionsResolver $resolver): void;

    /**
     * Translates a stored tl_flare_filter row into canonical config values (unresolved).
     * All deserialization, casting, and enum parsing belongs here.
     *
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    public function configFromRow(array $row): array;
}
