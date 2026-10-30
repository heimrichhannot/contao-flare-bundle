<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\EventListener\DataContainer\FlareFilter;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\StringUtil;
use HeimrichHannot\FlareBundle\DataContainer\FilterContainer;
use HeimrichHannot\FlareBundle\DataContainer\Inspection\FilterIssueReporter;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as TwigEnvironment;

/**
 * @internal For internal use only. Do not call this class or its methods directly.
 */
readonly class ListCallbacks
{
    public function __construct(
        private FilterIssueReporter $filterIssueReporter,
        private TranslatorInterface $translator,
        private TwigEnvironment     $twig,
    ) {}

    #[AsCallback(FilterContainer::TABLE_NAME, 'list.label.group')]
    public function listLabelGroup(string $group, string $mode, string $field, array $record, DataContainer $dc): string
    {
        return '';
    }

    #[AsCallback(FilterContainer::TABLE_NAME, 'list.sorting.child_record')]
    public function listLabelLabel(array $row): string
    {
        // A filter without a control is intrinsic.
        $isIntrinsic = ($row['control'] ?? '') === '';
        $isPublished = $row['published'] ?? false;

        $title = StringUtil::specialchars($row['title'] ?? '');

        if ($type = $row['type'] ?? null) {
            $typeLabel = StringUtil::specialchars($this->translator->trans($type, [], 'flare_filter'));
        }
        $typeLabel ??= 'N/A';

        $cutLabel = $this->translator->trans('DCA.cut.0', [$row['id']], 'contao_default');

        $filterAlias = FilterModel::generateFormName($row);

        return $this->twig->render('@HeimrichHannotFlare/backend/filter_child_record.html.twig', [
            'row' => $row,
            'is_intrinsic' => $isIntrinsic,
            'is_published' => $isPublished,
            'title' => $title,
            'type_label' => $typeLabel,
            'cut_label' => $cutLabel,
            'filter_alias' => $filterAlias,
            'notices' => $this->filterIssueReporter->getRowNotices($row),
        ]);
    }
}
