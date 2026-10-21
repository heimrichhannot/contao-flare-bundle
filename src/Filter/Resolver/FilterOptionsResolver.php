<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Resolver;

use HeimrichHannot\FlareBundle\Config\SchemaResolver;
use HeimrichHannot\FlareBundle\Contract\OptionsContract;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\Control\FilterControlInterface;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;

/**
 * Resolves a filter's canonical element or control config through its owner's declared schema.
 * Owners without an {@see OptionsContract} receive their config verbatim (unvalidated).
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
     * @throws FilterException If the config does not satisfy the owner's schema.
     */
    public function resolve(FilterElementInterface|FilterControlInterface $owner, array $config): array
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
                \sprintf(
                    '[FLARE] Invalid filter config for %s "%s": %s',
                    $owner instanceof FilterControlInterface ? 'control' : 'element',
                    $owner::class,
                    $e->getMessage(),
                ),
                previous: $e,
                method: $owner::class . '::configureOptions',
            );
        }
    }
}
