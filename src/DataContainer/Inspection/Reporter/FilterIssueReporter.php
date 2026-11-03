<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\DataContainer\Inspection\Reporter;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Message;
use Contao\StringUtil;
use HeimrichHannot\FlareBundle\DataContainer\Inspection\FilterIssueKind;
use HeimrichHannot\FlareBundle\DataContainer\Inspection\Inspector\FilterInspector;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Presents the issues of a list's filters ({@see FilterInspector}) in the backend: the counts above the list's filter
 * overview, the issues on a filter's edit form, and the notices on each filter row.
 *
 * Messages are added on GET requests only: the DCA is loaded before Contao saves a submitted form, and a message
 * added then would be flashed across the redirect, reporting the state from before the save.
 *
 * @internal
 */
final readonly class FilterIssueReporter
{
    public function __construct(
        private FilterInspector     $filterInspector,
        private ContaoFramework     $framework,
        private RequestStack        $requestStack,
        private TranslatorInterface $translator,
    ) {}

    /**
     * On the list's filter overview (its child view, a GET without `act`): how many published filters have an issue
     * of each kind, in the kinds' order; each filter row is marked in the overview as well.
     */
    public function reportOverview(ListModel $listModel): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request?->isMethod('GET')
            || $request->query->get('table') !== FilterModel::getTable()
            || $request->query->get('act')
        ) {
            return;
        }

        $inspection = $this->filterInspector->inspect($listModel);

        foreach (FilterIssueKind::cases() as $kind)
        {
            if (!$count = $inspection->count($kind)) {
                continue;
            }

            $parameters = match ($kind) {
                FilterIssueKind::DuplicateAlias => ['%alias%' => \implode(', ', \array_map(
                    static fn (string $alias): string => '"' . StringUtil::specialchars($alias) . '"',
                    $inspection->blockingAliases,
                ))],
                default => ['%count%' => $count],
            };

            $id = match ($kind) {
                FilterIssueKind::ProgrammingError => 'list.info.programming_errors',
                FilterIssueKind::DuplicateAlias => 'list.info.duplicate_filter_alias',
                FilterIssueKind::Broken => 'list.info.broken_filters',
                FilterIssueKind::Ineffective => 'list.info.ineffective_filters',
            };

            $this->addMessage($kind, $this->translator->trans($id, $parameters, 'flare'));
        }
    }

    /**
     * On the filter's edit form (a GET with `act=edit`): each of its issues, in its kind's severity.
     */
    public function reportFilter(ListModel $listModel, FilterModel $filterModel): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request?->isMethod('GET') || $request->query->get('act') !== 'edit') {
            return;
        }

        foreach ($this->filterInspector->inspect($listModel)->forRow((int) $filterModel->id) as $issue) {
            $this->addMessage($issue->kind, StringUtil::specialchars($issue->describe($this->translator)));
        }
    }

    /**
     * The text of each of the filter row's issues, by issue kind value; unescaped (the template escapes it).
     *
     * @param array<string, mixed> $row
     *
     * @return array<string, string>
     */
    public function getRowNotices(array $row): array
    {
        if (!$listModel = $this->framework->getAdapter(ListModel::class)->findByPk((int) ($row['pid'] ?? 0))) {
            return [];
        }

        $notices = [];

        foreach ($this->filterInspector->inspect($listModel)->forRow((int) ($row['id'] ?? 0)) as $issue) {
            $notices[$issue->kind->value] = $issue->describe($this->translator);
        }

        return $notices;
    }

    /**
     * Contao renders messages raw; the caller escapes what it passes.
     */
    private function addMessage(FilterIssueKind $kind, string $message): void
    {
        $messages = $this->framework->getAdapter(Message::class);

        if ($kind === FilterIssueKind::Ineffective)
        {
            $messages->addNew($message);

            return;
        }

        $messages->addError($message);
    }
}
