<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Lists\Resolver;

use HeimrichHannot\FlareBundle\Contract\OptionsContract;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Lists\BaseListOptions;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Resolves a list's canonical config through the framework's base schema plus the list
 * type's declared schema ({@see OptionsContract}). The combined resolver is memoized
 * per type class.
 */
class ListOptionsResolver
{
    /**
     * @var array<string, OptionsResolver> Keyed by type class; '' for type-less lists.
     */
    private array $resolvers = [];

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     *
     * @throws FlareException If the config does not satisfy the schema.
     */
    public function resolve(?object $typeService, array $config, ?string $source = null): array
    {
        $key = $typeService ? $typeService::class : '';

        if (!isset($this->resolvers[$key]))
        {
            $resolver = new OptionsResolver();
            BaseListOptions::configureOptions($resolver);

            if ($typeService instanceof OptionsContract) {
                $typeService->configureOptions($resolver);
            }

            $this->resolvers[$key] = $resolver;
        }

        try
        {
            return $this->resolvers[$key]->resolve($config);
        }
        catch (\Throwable $e)
        {
            throw new FlareException(
                \sprintf(
                    '[FLARE] Invalid list config%s: %s',
                    $typeService ? ' for list type "' . $typeService::class . '"' : '',
                    $e->getMessage(),
                ),
                previous: $e,
                method: ($typeService ? $typeService::class : BaseListOptions::class) . '::configureOptions',
                source: $source,
            );
        }
    }
}
