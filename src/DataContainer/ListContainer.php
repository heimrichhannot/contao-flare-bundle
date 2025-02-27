<?php

namespace HeimrichHannot\FlareBundle\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use HeimrichHannot\FlareBundle\List\ListTypeRegistry;
use Symfony\Contracts\Translation\TranslatorInterface;

class ListContainer
{
    public const TABLE_NAME = 'tl_flare_list';

    public function __construct(
        private readonly ListTypeRegistry $listTypeRegistry,
    ) {}

    #[AsCallback(self::TABLE_NAME, 'fields.type.options')]
    public function getTypeOptions(): array
    {
        $options = [];

        foreach ($this->listTypeRegistry->all() as $alias => $filterElement)
        {
            $service = $filterElement->getService();
            $options[$alias] = \class_implements($service, TranslatorInterface::class)
                ? $filterElement->getService()->trans($alias)
                : $alias;
        }

        return $options;
    }
}