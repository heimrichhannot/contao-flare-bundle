<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\DataContainer\Inspection;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * One issue of a stored filter, as the backend reports it on the filter's row and edit form.
 *
 * @internal
 */
final readonly class FilterIssue
{
    /**
     * @param string $reason The cause; for a duplicate alias the alias, for an ineffective filter the already
     *   translated reason.
     * @param bool $published Whether the filter is published; an unpublished filter's issue only applies once
     *   it is published.
     */
    public function __construct(
        public FilterIssueKind $kind,
        public string          $reason,
        public bool            $published,
    ) {}

    /**
     * The issue's text for the row tooltip and the edit form; not escaped.
     */
    public function describe(TranslatorInterface $translator): string
    {
        $state = $this->published ? 'published' : 'unpublished';

        return match ($this->kind) {
            FilterIssueKind::Ineffective => $this->reason,
            FilterIssueKind::DuplicateAlias => $translator->trans(
                "filter.info.duplicate_alias.{$state}",
                ['%alias%' => $this->reason],
                'flare',
            ),
            default => $translator->trans(
                "filter.info.{$this->kind->value}.{$state}",
                ['%reason%' => $this->reason],
                'flare',
            ),
        };
    }
}
