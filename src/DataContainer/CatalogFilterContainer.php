<?php

namespace HeimrichHannot\FlareBundle\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use HeimrichHannot\FlareBundle\Manager\FlareFilterElementManager;
use Symfony\Contracts\Translation\TranslatorInterface;

class CatalogFilterContainer
{
    public const TABLE_NAME = 'tl_flare_catalog_filter';

    public function __construct(
        private readonly FlareFilterElementManager $filterElementManager,
        private readonly TranslatorInterface $translator
    ) {}

    #[AsCallback(self::TABLE_NAME, 'fields.type.options')]
    public function getTypeOptions(): array
    {
        $filterElements = $this->filterElementManager->getFilterElements();

        $options = [];

        foreach ($filterElements as $filterElement)
        {
            $options[$filterElement->getAlias()] = $filterElement
                ->getAttribute()
                ->trans($this->translator);
        }

        return $options;
    }
}