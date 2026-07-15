<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List\Resolver;

use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\List\ListDriverReference;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\Registry\ListDriverRegistry;

final readonly class ListDriverResolver
{
    public function __construct(
        private ListDriverRegistry $registry,
    ) {}

    /**
     * @throws FlareException In case it's not possible to resolve the type of the list.
     */
    public function resolve(ListDriverInterface|string $driver): ListDriverReference
    {
        if ($driver instanceof ListDriverInterface) {
            return $this->resolveInstance($driver);
        }

        return $this->resolveType($driver);
    }

    private function resolveInstance(ListDriverInterface $driver): ListDriverReference
    {
        return ListDriverReference::inline($driver);
    }

    /**
     * @throws FlareException In case it's not possible to resolve the type of the list.
     */
    private function resolveType(string $type): ListDriverReference
    {
        if (!$descriptor = $this->registry->get($type)) {
            throw new FlareException(\sprintf(
                'List type "%s" not found',
                $type,
            ));
        }

        return ListDriverReference::registered($type, $descriptor->getService());
    }
}
