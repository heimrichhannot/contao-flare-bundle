<?php

namespace HeimrichHannot\FlareBundle\DataContainer;

use Contao\CoreBundle\Config\ResourceFinderInterface;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use HeimrichHannot\FlareBundle\List\ListTypeRegistry;
use Symfony\Contracts\Translation\TranslatorInterface;

class ListContainer
{
    public const TABLE_NAME = 'tl_flare_list';

    public function __construct(
        private readonly ListTypeRegistry $listTypeRegistry,
        private readonly ResourceFinderInterface $resourceFinder,
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

    #[AsCallback(self::TABLE_NAME, 'fields.dc.options')]
    public function getDataContainerOptions(): array
    {
        $choices = [];

        $files = $this->resourceFinder->findIn('dca')->name('tl_*.php');

        foreach ($files as $file) {
            $name = $file->getBasename('.php');
            $choices[$name] = $name;
        }

        \ksort($choices);

        return $choices;
    }
}