<?php

namespace HeimrichHannot\FlareBundle\EventListener\DataContainer\FlareFilter;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\StringUtil;
use HeimrichHannot\FlareBundle\DataContainer\FilterContainer;
use HeimrichHannot\FlareBundle\Manager\TranslationManager;
use Twig\Environment as TwigEnvironment;

/**
 * @internal For internal use only. Do not call this class or its methods directly.
 */
readonly class ListCallbacks
{
    public function __construct(
        private TranslationManager $translationManager,
        private TwigEnvironment    $twig,
    ) {}

    #[AsCallback(FilterContainer::TABLE_NAME, 'list.label.group')]
    public function listLabelGroup(string $group, string $mode, string $field, array $record, DataContainer $dc): string
    {
        return '';
    }

    #[AsCallback(FilterContainer::TABLE_NAME, 'list.sorting.child_record')]
    public function listLabelLabel(array $row): string
    {
        $isIntrinsic = $row['intrinsic'] ?? false;
        $isPublished = $row['published'] ?? false;

        $title = StringUtil::specialchars($row['title'] ?? '');

        if ($type = $row['type'] ?? null) {
            $typeLabel = StringUtil::specialchars($this->translationManager->filterElement($type));
        }
        $typeLabel ??= 'N/A';

        $formAlias = ($row['formAlias'] ?? null) ?: ($row['id'] ?? null);

        return $this->twig->render('@HeimrichHannotFlare/be_filter_info.html.twig', [
            'row' => $row,
            'is_intrinsic' => $isIntrinsic,
            'is_published' => $isPublished,
            'title' => $title,
            'type_label' => $typeLabel,
            'form_alias' => $formAlias,
        ]);
    }
}