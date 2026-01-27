<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Manager;

use Contao\PageModel;
use HeimrichHannot\FlareBundle\DataContainer\ListContainer;
use HeimrichHannot\FlareBundle\Dto\ContentContext;
use HeimrichHannot\FlareBundle\Event\ListViewDetailsPageUrlGeneratedEvent;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Util\CallbackHelper;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Class ListViewManager
 *
 * Manages the list view, including filters, forms, pagination, sort-order, and entries.
 */
final class ListViewManager
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * Get the URL of the details page of a particular entry for a given list model, form name, and entry ID.
     *
     * @param int            $id The entry ID.
     * @param ListModel      $listModel The list model.
     * @param ContentContext $contentContext The content context.
     *
     * @return string|null The URL of the details page, or null if not found.
     *
     * @throws FlareException If the details page is not found.
     */
    public function getDetailsPageUrl(
        int            $id,
        ListModel      $listModel,
        ContentContext $contentContext,
    ): ?string {
        if (!$pageId = (int) ($listModel->jumpToReader ?: 0)) {
            return null;
        }

        $autoItemField = $listModel->getAutoItemField();
        $model = $this->getModel(
            id: $id,
            listDefinition: $listModel,
            contentContext: $contentContext
        );

        if (!$autoItem = (string) CallbackHelper::tryGetProperty($model, $autoItemField)) {
            return null;
        }

        if (!$page = PageModel::findByPk($pageId)) {
            throw new FlareException(\sprintf('Details page not found [ID %s]', $pageId), source: __METHOD__);
        }

        $url = $page->getAbsoluteUrl('/' . $autoItem);

        $event = $this->eventDispatcher->dispatch(
            new ListViewDetailsPageUrlGeneratedEvent(
                listModel: $listModel,
                contentContext: $contentContext,
                model: $model,
                autoItem: $autoItem,
                page: $page,
                url: $url,
            )
        );

        return $event->getUrl();
    }

    /**
     * Create a cache key for a given list model, form name, and additional arguments.
     *
     * @param ListModel      $listModel The list model.
     * @param ContentContext $context The content context.
     * @param mixed          ...$args Additional arguments that should be part of the cache key (optional).
     *
     * @return string The cache key.
     */
    public function makeCacheKey(ListModel $listModel, ?ContentContext $context, mixed ...$args): string
    {
        $args = \array_filter($args);
        $parts = [ListContainer::TABLE_NAME . '.' . $listModel->id, $context?->getUniqueId(), ...$args];
        $parts = \array_filter($parts);
        return \implode('@', $parts);
    }
}