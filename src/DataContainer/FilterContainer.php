<?php

namespace HeimrichHannot\FlareBundle\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use HeimrichHannot\FlareBundle\FilterElement\FilterElementRegistry;
use Symfony\Contracts\Translation\TranslatorInterface;

class FilterContainer
{
    public const TABLE_NAME = 'tl_flare_filter';

    public function __construct(
        private readonly FilterElementRegistry $filterElementRegistry,
        private readonly TranslatorInterface   $translator
    ) {}

    #[AsCallback(self::TABLE_NAME, 'fields.type.options')]
    public function getTypeOptions(): array
    {
        $options = [];

        foreach ($this->filterElementRegistry->all() as $alias => $filterElement)
        {
            $options[$alias] = $filterElement->getService()->trans($alias);
        }

        return $options;
    }

    #[AsCallback(self::TABLE_NAME, 'list.label.group')]
    public function listLabelGroup(string $group, string $mode, string $field, array $record, DataContainer $dc): string
    {
        return '';
    }

/*    #[AsCallback(self::TABLE_NAME, 'list.sorting.child_record')]
    public function listLabelLabel(array $row): string
    {
        $key = $row['published'] ? 'published' : 'unpublished';

        $cls2 = !\Contao\Config::get('doNotCollapse') ? 'h40' : '';
        $title = \Contao\StringUtil::specialchars($row['title']);

        return <<<HTML
            <div class="cte_type $key">HALLO</div>
            <div class="limit_height $cls2">
                <h2>$title</h2>
            </div>
        HTML;
    }*/
}