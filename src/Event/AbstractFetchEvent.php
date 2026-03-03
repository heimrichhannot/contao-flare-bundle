<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Collection\FilterDefinitionCollection;
use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\ListItemProvider\ListItemProviderInterface;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use Symfony\Contracts\EventDispatcher\Event;

/** @deprecated */
abstract class AbstractFetchEvent extends Event
{
    public function __construct(
        private readonly ContextInterface  $contextConfig,
        private readonly ListSpecification $listSpecification,
    ) {}

    public function getContextConfig(): ContextInterface
    {
        return $this->contextConfig;
    }

    public function getListSpecification(): ListSpecification
    {
        return $this->listSpecification;
    }

    public function getItemProvider(): ListItemProviderInterface
    {
        return $this->itemProvider;
    }

    public function setItemProvider(ListItemProviderInterface $itemProvider): void
    {
        $this->itemProvider = $itemProvider;
    }

    public function getListQueryBuilder(): mixed
    {
        return null;
    }

    /** @deprecated use {@see self::getListSpecification()->getFilters()} instead} */
    public function getFilters(): FilterDefinitionCollection
    {
        return $this->getListSpecification()->getFilters();
    }
}