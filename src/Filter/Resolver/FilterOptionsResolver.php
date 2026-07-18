<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Resolver;

use HeimrichHannot\FlareBundle\Config\SchemaResolver;
use HeimrichHannot\FlareBundle\Contract\OptionsContract;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\Filter;

/**
 * Resolves a filter's canonical config through the element's declared schema.
 * Elements without an {@see OptionsContract} receive their config verbatim (unvalidated).
 */
final readonly class FilterOptionsResolver
{
    public function __construct(
        private SchemaResolver $schemaResolver,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws FilterException If the config does not satisfy the element's schema.
     */
    public function resolve(Filter $filter): array
    {
        $element = $filter->element;

        if (!$element instanceof OptionsContract) {
            return $filter->config;
        }

        try
        {
            return $this->schemaResolver->resolve($element::class, $element->configureOptions(...), $filter->config);
        }
        catch (\Throwable $e)
        {
            throw new FilterException(
                \sprintf('[FLARE] Invalid filter config for element "%s": %s', $element::class, $e->getMessage()),
                previous: $e,
                method: $element::class . '::configureOptions',
                source: $filter->source,
            );
        }
    }
}
