<?php

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Dto\ContentContext;
use HeimrichHannot\FlareBundle\Filter\FilterContextCollection;
use HeimrichHannot\FlareBundle\Filter\FilterDefinitionCollection;
use HeimrichHannot\FlareBundle\List\ListContext;
use HeimrichHannot\FlareBundle\List\ListDefinition;
use HeimrichHannot\FlareBundle\List\ListQueryBuilder;
use HeimrichHannot\FlareBundle\ListItemProvider\ListItemProviderInterface;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;
use HeimrichHannot\FlareBundle\SortDescriptor\SortDescriptor;
use Symfony\Component\Form\FormInterface;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractFetchEvent extends Event
{
    public function __construct(
        private readonly ListContext       $listContext,
        private readonly ListDefinition    $listDefinition,
        private ListItemProviderInterface  $itemProvider,
        private ListQueryBuilder           $listQueryBuilder,
    ) {}

    public function getListContext(): ListContext
    {
        return $this->listContext;
    }

    public function getListDefinition(): ListDefinition
    {
        return $this->listDefinition;
    }

    public function getItemProvider(): ListItemProviderInterface
    {
        return $this->itemProvider;
    }

    public function setItemProvider(ListItemProviderInterface $itemProvider): void
    {
        $this->itemProvider = $itemProvider;
    }

    public function getListQueryBuilder(): ListQueryBuilder
    {
        return $this->listQueryBuilder;
    }

    public function setListQueryBuilder(ListQueryBuilder $listQueryBuilder): void
    {
        $this->listQueryBuilder = $listQueryBuilder;
    }

    /** @deprecated use {@see self::getListDefinition()->getFilters()} instead} */
    public function getFilters(): FilterDefinitionCollection
    {
        return $this->getListDefinition()->getFilters();
    }
}