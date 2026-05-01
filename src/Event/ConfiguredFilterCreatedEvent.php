<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Specification\ConfiguredFilter;
use Symfony\Contracts\EventDispatcher\Event;

class ConfiguredFilterCreatedEvent extends Event
{
    public function __construct(
        public ConfiguredFilter $configuredFilter,
    ) {}
}