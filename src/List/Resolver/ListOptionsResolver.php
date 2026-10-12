<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List\Resolver;

use HeimrichHannot\FlareBundle\Config\SchemaResolver;
use HeimrichHannot\FlareBundle\Contract\OptionsContract;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\List\BaseListOptions;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Resolves a list's canonical config through the framework's base schema plus the list
 * type's declared schema ({@see OptionsContract}). The combined resolver is memoized
 * per type class.
 */
final readonly class ListOptionsResolver
{
    public function __construct(
        private SchemaResolver $schemaResolver,
    ) {}

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     *
     * @throws FlareException If the config does not satisfy the schema.
     */
    public function resolve(?ListDriverInterface $driverService, array $config, ?string $source = null): array
    {
        $configure = static function (OptionsResolver $resolver) use ($driverService): void {
            BaseListOptions::configureOptions($resolver);

            if ($driverService instanceof OptionsContract) {
                $driverService->configureOptions($resolver);
            }
        };

        $driverClass = $driverService ? $driverService::class : null;

        try
        {
            return $this->schemaResolver->resolve((string) $driverClass, $configure, $config);
        }
        catch (\Throwable $e)
        {
            throw new FlareException(
                \sprintf(
                    '[FLARE] Invalid list config%s: %s',
                    $driverService ? ' for list type "' . $driverService::class . '"' : '',
                    $e->getMessage(),
                ),
                previous: $e,
                method: ($driverClass ?? BaseListOptions::class) . '::configureOptions',
                source: $source,
            );
        }
    }
}
