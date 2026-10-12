<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use Contao\Model;
use Contao\PageModel;
use Symfony\Contracts\EventDispatcher\Event;

class DetailsPageUrlGeneratedEvent extends Event
{
    public function __construct(
        public readonly Model $model,
        public string         $autoItem,
        public PageModel      $page,
        public string         $url,
    ) {}
}
