<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\EventListener\Inspection;

use HeimrichHannot\FlareBundle\Contao\Inspection\FilterIssueIcon;
use HeimrichHannot\FlareBundle\Event\FilterInspectionEvent;
use HeimrichHannot\FlareBundle\Filter\Element\PublishedFilterElement;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Warns on a publication filter exclusive to an output when the list has no publication filter that is not
 * exclusive: the outputs it does not apply to (the reader, the lookup) show unpublished entries too. Intended if the
 * editor means it, so a warning, not an error. Exclusive to no output, the filter is already reported as
 * ineffective and gets no warning.
 */
#[AsEventListener]
final readonly class ExclusivePublishedFilterListener
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {}

    public function __invoke(FilterInspectionEvent $event): void
    {
        $filter = $event->filter;

        if ($filter === null
            || !$filter->element->component instanceof PublishedFilterElement
            || !$filter->isExclusive()
            || $filter->outputs === []
            || $event->list === null
        ) {
            return;
        }

        foreach ($event->list->filters as $other)
        {
            if ($other->element->component instanceof PublishedFilterElement && !$other->isExclusive()) {
                return;
            }
        }

        $event->addWarning(
            $this->translator->trans('filter.info.warning.exclusive_published', [], 'flare'),
            FilterIssueIcon::Opacity,
        );
    }
}
