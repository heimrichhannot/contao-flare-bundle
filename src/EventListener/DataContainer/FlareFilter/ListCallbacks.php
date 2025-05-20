<?php

namespace HeimrichHannot\FlareBundle\EventListener\DataContainer\FlareFilter;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\StringUtil;
use HeimrichHannot\FlareBundle\DataContainer\FilterContainer;
use HeimrichHannot\FlareBundle\Manager\TranslationManager;

/**
 * @internal For internal use only. Do not call this class or its methods directly.
 */
readonly class ListCallbacks
{
    public function __construct(
        private TranslationManager $translationManager,
    ) {}

    #[AsCallback(FilterContainer::TABLE_NAME, 'list.label.group')]
    public function listLabelGroup(string $group, string $mode, string $field, array $record, DataContainer $dc): string
    {
        return '';
    }

    #[AsCallback(FilterContainer::TABLE_NAME, 'list.sorting.child_record')]
    public function listLabelLabel(array $row): string
    {
        $key = $row['published'] ? 'published' : 'unpublished';

        $title = StringUtil::specialchars($row['title'] ?? '');

        if ($type = $row['type'] ?? null)
        {
            $typeLabel = StringUtil::specialchars($this->translationManager->filterElement($type));
        }

        $typeLabel ??= 'N/A';

        $html = "<div class=\"cte_type $key\">[$typeLabel]</div>";
        $html .= $title ? "<div><strong>$title</strong></div>" : '';

        return $html;
    }
}