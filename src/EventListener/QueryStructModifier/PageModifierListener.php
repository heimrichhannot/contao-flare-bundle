<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\EventListener\QueryStructModifier;

use HeimrichHannot\FlareBundle\Engine\Context\Interface\PaginatedContextInterface;
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

        $context = $event->config->context;
        if (!$context instanceof PaginatedContextInterface) {
            return;
        }

        $paginator = $context->getPaginatorConfig();
        $event->queryStruct->setLimit($paginator->getItemsPerPage());
        $event->queryStruct->setOffset($paginator->getOffset());
    }
}