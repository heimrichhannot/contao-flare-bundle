<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Resolver;

use HeimrichHannot\FlareBundle\Config\SchemaResolver;
use HeimrichHannot\FlareBundle\Contract\OptionsContract;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterComponentInterface;

/**
 * Resolves the canonical config of a filter component (element or control) through its declared schema.
 * Components without an {@see OptionsContract} receive their config verbatim (unvalidated).
 */
final readonly class FilterOptionsResolver
{
    public function __construct(
        private SchemaResolver $schemaResolver,
    ) {}

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     *
     * @throws FilterException If the config does not satisfy the component's schema.
     */
    public function resolve(FilterComponentInterface $owner, array $config): array
    {
        if (!$owner instanceof OptionsContract) {
            return $config;
        }

        try
        {
            return $this->schemaResolver->resolve($owner::class, $owner->configureOptions(...), $config);
        }
        catch (\Throwable $e)
        {
            throw new FilterException(
                \sprintf('[FLARE] Invalid config for "%s": %s', $owner::class, $e->getMessage()),
                previous: $e,
                method: $owner::class . '::configureOptions',
            );
        }
    }
}
