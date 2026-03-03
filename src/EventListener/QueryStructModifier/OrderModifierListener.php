<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\EventListener\QueryStructModifier;

use HeimrichHannot\FlareBundle\Engine\Context\Interface\SortableContextInterface;
use HeimrichHannot\FlareBundle\Event\ModifyListQueryStructEvent;
use HeimrichHannot\FlareBundle\Sort\SortOrder;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(priority: 420)]
readonly class OrderModifierListener
{
    public function __invoke(ModifyListQueryStructEvent $event): void
    {
        if ($event->config->isCounting) {
            return;
        }

        $context = $event->config->context;
        if (!$context instanceof SortableContextInterface) {
            return;
        }

        if (!$sortOrderSequence = $context->getSortOrderSequence()) {
            return;
        }

        $order = \array_map(
            static fn (SortOrder $o): array => [$o->getQualifiedColumn(), $o->getDirection()],
            $sortOrderSequence->getItems()
        );

        $event->queryStruct->setOrderBy($order);
    }
}