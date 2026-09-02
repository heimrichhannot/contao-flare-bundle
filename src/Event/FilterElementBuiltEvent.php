<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use Symfony\Contracts\EventDispatcher\Event;

class FilterElementBuiltEvent extends Event
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly FilterContext          $context,
        public readonly FilterBuilderInterface $builder,
        public readonly array                  $data = [],
    ) {}
}
