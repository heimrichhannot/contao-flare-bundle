<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List\Factory;

use Contao\Controller;
use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Event\FilterCollectedEvent;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterFactory;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\List\BaseListOptions;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\List\Resolver\ListOptionsResolver;
use HeimrichHannot\FlareBundle\List\Resolver\ListTransformerResolver;
use HeimrichHannot\FlareBundle\Model\FilterModel;
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
        $type = $this->resolveType($driver);
        $driver = $this->resolveDriver($driver);

        $config = $this->listOptionsResolver->resolve($driver, $config, $source);

        $dc = $this->resolveDataContainer($config, $driver, $type, $source);

        return new ListSpec(
            driver: $driver,
            type: $type,
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
        ?ListModel                      $listModel,
        ListDriverInterface|string|null $driver = null,
        array                           $filters = [],
        array                           $config = [],
        ?string                         $source = null,
    ): ListSpec {
        $driver ??= $listModel->getListDriverType();
        $type = $this->resolveType($driver);
        $driver = $this->resolveDriver($driver);

        $configBuilder = new ConfigBuilder();

        BaseListOptions::transform($configBuilder, $listModel);

        $transformed = $this->transformerResolver->transform($driver, $type, $listModel);

        foreach ($transformed ?? [] as $key => $value) {
            $configBuilder->set($key, $value);
        }

        foreach ($config as $key => $value) {
            $configBuilder->set($key, $value);
        }

        $finalConfig = $this->listOptionsResolver->resolve($driver, $configBuilder->all(), $source);

        $dc = $this->resolveDataContainer($finalConfig, $driver, $type, $source);

        return new ListSpec(
            driver: $driver,
            type: $type,
            dc: $dc,
            filters: $filters,
            config: $finalConfig,
            source: $source,
        );
    }

    /**
     * @throws FlareException
     */
    private function resolveType(ListDriverInterface|string $driver, ?string $source = null): string
    {
        if (!$type = \is_object($driver) ? \get_class($driver) : (string) $driver)
        {
            throw new FlareException(\sprintf(
                'A list driver instance or registered type alias must be provided%s.',
                $source ? " ($source)" : '',
            ), method: __METHOD__);
        }

        return $type;
    }

    /**
     * @throws FlareException In case no driver is registered under the given type alias.
     */
    private function resolveDriver(ListDriverInterface|string $driver, ?string $source = null): ListDriverInterface
    {
        if ($driver instanceof ListDriverInterface) {
            return $driver;
        }

        return $this->listDriverRegistry->getService($driver)
            ?? throw new FlareException(\sprintf(
                'List type "%s" not found%s.',
                $driver,
                $source ? " ($source)" : ''
            ), method: __METHOD__);
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
        $attributes = $this->listDriverRegistry->getAttribute($type)?->attributes ?? [];

        if (!$dc = $driver->resolveDcTable($type, $config, $attributes))
        {
            throw new FlareException(\sprintf(
                'Failed to evaluate data container table of list type "%s"%s.',
                $type,
                $source ? " ($source)" : ''
            ), method: __METHOD__);
        }

        return $dc;
    }
}
