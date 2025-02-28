<?php

namespace HeimrichHannot\FlareBundle\DataContainer;

use Contao\CoreBundle\Asset\ContaoContext;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use HeimrichHannot\FlareBundle\Filter\FilterElementRegistry;
use HeimrichHannot\UtilsBundle\Util\Utils;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class FilterContainer
{
    public const TABLE_NAME = 'tl_flare_filter';

    public function __construct(
        private readonly FilterElementRegistry $filterElementRegistry,
        private readonly RequestStack $requestStack,
    ) {}

    #[AsCallback(self::TABLE_NAME, 'fields.type.options')]
    public function getTypeOptions(): array
    {
        $options = [];

        foreach ($this->filterElementRegistry->all() as $alias => $filterElement)
        {
            $service = $filterElement->getService();
            $options[$alias] = \class_implements($service, TranslatorInterface::class)
                ? $service->trans($alias)
                : $alias;
        }

        return $options;
    }

    #[AsCallback(self::TABLE_NAME, 'fields.intrinsic.load')]
    public function intrinsic_load(mixed $value, DataContainer $dc): bool
    {
        $value = (bool) $value;

        $request = $this->requestStack->getCurrentRequest();
        if ($request->getMethod() === 'POST' && $request->request->get('FORM_SUBMIT') === self::TABLE_NAME)
        {
            // do not disable intrinsic field if form is being submitted
            // otherwise the save callback will not be called
            return $value;
        }

        if (!$row = $dc->activeRecord?->row()) {
            return $value;
        }

        if ($this->filterElementRegistry->get($row['type'] ?? null)?->isIntrinsicRequired())
        {
            $eval = &$GLOBALS['TL_DCA'][self::TABLE_NAME]['fields']['intrinsic']['eval'];

            $eval['disabled'] = true;

            return true;
        }

        return $value;
    }

    #[AsCallback(self::TABLE_NAME, 'fields.intrinsic.save')]
    public function intrinsic_save(mixed $value, DataContainer $dc): mixed
    {
        if ($value || !$row = $dc->activeRecord?->row()) {
            return $value;
        }

        if ($this->filterElementRegistry->get($row['type'] ?? null)?->isIntrinsicRequired()) {
            return '1';
        }

        return $value;
    }

    #[AsCallback(self::TABLE_NAME, 'list.label.group')]
    public function listLabelGroup(string $group, string $mode, string $field, array $record, DataContainer $dc): string
    {
        return '';
    }

    /* #[AsCallback(self::TABLE_NAME, 'list.sorting.child_record')]
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