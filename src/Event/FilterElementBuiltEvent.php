<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Filter\FormulaBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterData;
use Symfony\Contracts\EventDispatcher\Event;

class FilterElementBuiltEvent extends Event
{
    public function __construct(
        public readonly FilterContext           $context,
        public readonly FormulaBuilderInterface $builder,
        public readonly FilterData              $data,
    ) {}
}
