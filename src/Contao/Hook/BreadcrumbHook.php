<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contao\Hook;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\Module;
use HeimrichHannot\FlareBundle\Contao\Reader\ReaderResolver;
use HeimrichHannot\FlareBundle\Util\Env;

/**
 * Titles the last breadcrumb item after the entry of the page's reader, with the reader's page meta title. Leaves the
 * items unchanged without a reader, an auto-item, an entry or a title, and when the list failed.
 */
#[AsHook('generateBreadcrumb')]
readonly class BreadcrumbHook
{
    public function __construct(
        private ReaderResolver $readerResolver,
    ) {}

    public function __invoke(array $items, Module $module): array
    {
        $title = null;

        try
        {
            $title = $this->readerResolver->resolveForPage()?->getPageMeta()?->getTitle();
        }
        // @mago-expect lint:no-empty-catch-clause Intentionally ignored to not break the breadcrumb
        catch (\Exception)
        {
            // ignore
        }

        if ($title === null || $title === '' || $items === []) {
            return $items;
        }

        $items = \array_values($items);

        if (Env::isContao4()) {
            $items = self::dropRepeatedReaderPage($items);
        }

        $last = \count($items) - 1;
        $items[$last]['title'] = $title;
        $items[$last]['link'] = $title;

        return $items;
    }

    /**
     * On Contao 4.13 the reader page can end the breadcrumb twice: drops the second-to-last item when the last two
     * share their page id.
     *
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private static function dropRepeatedReaderPage(array $items): array
    {
        $count = \count($items);

        if ($count < 2) {
            return $items;
        }

        $lastPageId = (int) ($items[$count - 1]['data']['id'] ?? 0);
        $secondLastPageId = (int) ($items[$count - 2]['data']['id'] ?? 0);

        if ($lastPageId === 0 || $lastPageId !== $secondLastPageId) {
            return $items;
        }

        unset($items[$count - 2]);

        return \array_values($items);
    }
}
