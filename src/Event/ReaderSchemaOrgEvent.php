<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use Contao\Model;
use HeimrichHannot\FlareBundle\List\ListSpec;
use Symfony\Contracts\EventDispatcher\Event;

class ReaderSchemaOrgEvent extends Event
{
    public function __construct(
        public readonly ListSpec $list,
        public readonly Model    $model,
        public array             $data = [],
    ) {}
}
