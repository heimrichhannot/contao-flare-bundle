<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List\Resolver;

use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\ResolvedListDriver;
use HeimrichHannot\FlareBundle\Registry\ListDriverRegistry;

final readonly class ListDriverResolver
{
    public function __construct(private ListDriverRegistry $listDriverRegistry) {}

    public function resolve(ResolvedListDriver|ListDriverInterface|string $driver, ?string $source = null): ResolvedListDriver
    {
        if ($driver instanceof ResolvedListDriver) {
            return $driver;
        }

        $type = $this->resolveType($driver, $source);
        $driver = $this->resolveDriver($driver, $source);

        return new ResolvedListDriver($type, $driver);
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
                $source ? " ({$source})" : '',
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
                $source ? " ({$source})" : ''
            ), method: __METHOD__);
    }
}
