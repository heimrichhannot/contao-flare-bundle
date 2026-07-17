<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List\Factory;

use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\List\Resolver\ListOptionsResolver;
use HeimrichHannot\FlareBundle\Registry\ListDriverRegistry;

/**
 * The single construction path for {@see ListSpec}: resolves the driver from its type alias if
 * necessary, resolves the config through the base and driver schemas, and guarantees a
 * well-defined data container ({@see ListDriverInterface::getDataContainerName()}).
 */
final readonly class ListSpecFactory
{
    public function __construct(
        private ListDriverRegistry  $listDriverRegistry,
        private ListOptionsResolver $listOptionsResolver,
    ) {}

    /**
     * @param array<string, Filter> $filters
     * @param array<string, mixed> $config Canonical config values, unresolved.
     *
     * @throws FlareException In case the driver cannot be resolved, the config does not satisfy
     *                        the schema, or no data container can be determined.
     */
    public function create(
        ListDriverInterface|string $driver,
        array                      $filters = [],
        array                      $config = [],
        ?string                    $source = null,
    ): ListSpec {
        $driver = $this->resolveDriver($driver);

        $config = $this->listOptionsResolver->resolve($driver, $config, $source);

        if (!$dc = $driver->getDataContainerName($config))
        {
            throw new FlareException(
                \sprintf('Failed to evaluate data container table of list "%s".', $source ?? \get_class($driver)),
                method: __METHOD__,
            );
        }

        $config['dc'] = $dc;

        return new ListSpec(
            driver: $driver,
            filters: $filters,
            config: $config,
            source: $source,
        );
    }

    /**
     * @throws FlareException In case no driver is registered under the given type alias.
     */
    public function resolveDriver(ListDriverInterface|string $driver): ListDriverInterface
    {
        if ($driver instanceof ListDriverInterface) {
            return $driver;
        }

        return $this->listDriverRegistry->getService($driver)
            ?? throw new FlareException(\sprintf('List type "%s" not found', $driver));
    }
}
