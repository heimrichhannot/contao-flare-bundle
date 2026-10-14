<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List\Factory;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\List\BaseListOptions;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\List\ResolvedListDriver;
use HeimrichHannot\FlareBundle\List\Resolver\ListDriverResolver;
use HeimrichHannot\FlareBundle\List\Resolver\ListOptionsResolver;
use HeimrichHannot\FlareBundle\List\Resolver\ListTransformerResolver;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Registry\ListDriverRegistry;

/**
 * The single construction path for {@see ListSpec}: resolves the driver from its type alias if
 * necessary, resolves the config through the base and driver schemas, and guarantees a
 * well-defined data container ({@see ListDriverInterface::resolveDcTable()}).
 */
final readonly class ListSpecFactory
{
    public function __construct(
        private ListDriverRegistry      $listDriverRegistry,
        private ListOptionsResolver     $listOptionsResolver,
        private ListTransformerResolver $transformerResolver,
        private ListDriverResolver      $listDriverResolver,
    ) {}

    /**
     * @param array<string, Filter> $filters
     * @param array<string, mixed> $config Canonical config values, unresolved.
     *
     * @throws FlareException In case the driver cannot be resolved, the config does not satisfy
     *                        the schema, or no data container can be determined.
     */
    public function create(
        ResolvedListDriver|ListDriverInterface|string $driver,
        array                                         $filters = [],
        array                                         $config = [],
        ?string                                       $source = null,
    ): ListSpec {
        $resolved = $this->listDriverResolver->resolve($driver);
        $config = $this->listOptionsResolver->resolve($resolved->driver, $config, $source);
        $dc = $this->resolveDataContainer($config, $resolved->driver, $resolved->type, $source);

        return new ListSpec(
            driver: $resolved->driver,
            type: $resolved->type,
            dc: $dc,
            filters: $filters,
            config: $config,
            source: $source,
        );
    }

    /**
     * @throws FlareException In case the driver cannot be resolved, the config does not satisfy
     *                        the schema, or no data container can be determined.
     */
    public function createFromListModel(
        ?ListModel                                         $listModel,
        ResolvedListDriver|ListDriverInterface|string|null $driver = null,
        array                                              $filters = [],
        array                                              $config = [],
        ?string                                            $source = null,
    ): ListSpec {
        $driver ??= $listModel->getListDriverType();
        $resolved = $this->listDriverResolver->resolve($driver);

        $configBuilder = new ConfigBuilder();

        BaseListOptions::transform($configBuilder, $listModel);

        $transformed = $this->transformerResolver->transform($resolved->driver, $resolved->type, $listModel);

        foreach ($transformed ?? [] as $key => $value) {
            $configBuilder->set($key, $value);
        }

        foreach ($config as $key => $value) {
            $configBuilder->set($key, $value);
        }

        $finalConfig = $this->listOptionsResolver->resolve($resolved->driver, $configBuilder->all(), $source);

        $dc = $this->resolveDataContainer($finalConfig, $resolved->driver, $resolved->type, $source);

        return new ListSpec(
            driver: $resolved->driver,
            type: $resolved->type,
            dc: $dc,
            filters: $filters,
            config: $finalConfig,
            source: $source,
        );
    }

    /**
     * @throws FlareException
     */
    private function resolveDataContainer(
        array               $config,
        ListDriverInterface $driver,
        string              $type,
        ?string             $source = null
    ): string {
        $attributes = $this->listDriverRegistry->getAttribute($type)->attributes ?? [];

        if (!$dc = $driver->resolveDcTable($type, $config, $attributes))
        {
            throw new FlareException(\sprintf(
                'Failed to evaluate data container table of list type "%s"%s.',
                $type,
                $source ? " ({$source})" : ''
            ), method: __METHOD__);
        }

        return $dc;
    }
}
