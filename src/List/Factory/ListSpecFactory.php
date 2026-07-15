<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List\Factory;

use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\List\Resolver\ListDriverResolver;
use HeimrichHannot\FlareBundle\List\Type\ListDriverInterface;

final readonly class ListSpecFactory
{
    public function __construct(
        private ListDriverResolver $listDriverResolver,
    ) {}

    /**
     * @throws FlareException In case the list driver cannot be resolved.
     */
    public function create(
        ListDriverInterface|string $driver,
        ?string                    $dc = null,
        array                      $filters = [],
        array                      $config = [],
        ?string                    $source = null,
    ): ListSpec {
        return new ListSpec(
            reference: $this->listDriverResolver->resolve($driver),
            dc: $dc,
            filters: $filters,
            config: $config,
            source: $source,
        );
    }
}
