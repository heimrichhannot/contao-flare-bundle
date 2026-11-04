<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\EventListener\QueryStructModifier;

use HeimrichHannot\FlareBundle\Engine\Intent\PaginatedIntentInterface;
use HeimrichHannot\FlareBundle\Event\ModifyListQueryStructEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(priority: 430)]
readonly class PageModifierListener
{
    public function __invoke(ModifyListQueryStructEvent $event): void
    {
        if ($event->config->isCounting) {
            return;
        }

        $intent = $event->config->intent;
        if (!$intent instanceof PaginatedIntentInterface) {
            return;
        }

        $paginator = $intent->getPaginatorConfig();
        $event->queryStruct->setLimit($paginator->getItemsPerPage() ?: null); // unlimited: 0 -> null
        $event->queryStruct->setOffset($paginator->getOffset());
    }
}
