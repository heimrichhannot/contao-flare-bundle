<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List\Resolver;

use HeimrichHannot\FlareBundle\Config\SchemaResolver;
use HeimrichHannot\FlareBundle\Contract\OptionsContract;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\List\BaseListOptions;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Resolves a list's canonical config through the framework's base schema plus the list
 * type's declared schema ({@see OptionsContract}). The combined resolver is memoized
 * per type class.
 */
class ListOptionsResolver
{
    public function __construct(
        private readonly SchemaResolver $schemaResolver,
    ) {}

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     *
     * @throws FlareException If the config does not satisfy the schema.
     */
    public function resolve(?object $typeService, array $config, ?string $source = null): array
    {
        $configure = static function (OptionsResolver $resolver) use ($typeService): void {
            BaseListOptions::configureOptions($resolver);

            if ($typeService instanceof OptionsContract) {
                $typeService->configureOptions($resolver);
            }
        };

        try
        {
            return $this->schemaResolver->resolve($typeService ? $typeService::class : '', $configure, $config);
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
