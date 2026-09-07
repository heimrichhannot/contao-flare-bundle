<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterData;
use Symfony\Contracts\EventDispatcher\Event;

class FilterElementBuildingEvent extends Event
{
    public function __construct(
        public readonly FilterContext          $context,
        public readonly FilterBuilderInterface $builder,
        public readonly FilterData             $data,
        public bool                            $shouldBuild = true,
    ) {}
}
