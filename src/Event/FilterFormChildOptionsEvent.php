<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Specification\ConfiguredFilter;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use Symfony\Contracts\EventDispatcher\Event;

class FilterFormChildOptionsEvent extends Event
{
    public function __construct(
        public readonly ListSpecification $listSpecification,
        public readonly ConfiguredFilter  $configuredFilter,
        public readonly ?string           $parentFormName,
        public readonly string            $formName,
        public array                      $options,
    ) {}
}