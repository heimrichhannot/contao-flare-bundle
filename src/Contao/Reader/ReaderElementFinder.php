<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contao\Reader;

use Contao\ArticleModel;
use Contao\ContentModel;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\PageModel;
use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Controller\ContentElement\ReaderController;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Finds the reader element of the current page, for the breadcrumb: the single visible top-level `flare_reader`
 * element in the page's visible articles. Contao's preview mode is not honored.
 */
final readonly class ReaderElementFinder
{
    public function __construct(
        private Connection      $connection,
        private ContaoFramework $framework,
        private RequestStack    $requestStack,
    ) {}

    /**
     * The single visible top-level flare_reader element of the current page; null for none or several.
     */
    public function find(): ?ContentModel
    {
        if (($pageId = $this->getPageId()) === null) {
            return null;
        }

        $time = $this->framework->getAdapter(Date::class)->floorToMinute();

        // Written like ContentModel::findPublishedByPidAndTable(). Contao 4.13 stores article content with an empty
        // ptable.
        $rows = $this->connection->fetchAllAssociative(
            'SELECT c.id AS content_id, a.id AS article_id'
            . ' FROM tl_content c INNER JOIN tl_article a ON a.id = c.pid'
            . ' WHERE a.pid = :page AND c.type = :type AND c.ptable IN (:ptable, \'\')'
            . ' AND a.published = 1 AND (a.start = \'\' OR a.start <= :time) AND (a.stop = \'\' OR a.stop > :time)'
            . ' AND c.invisible = 0 AND c.tstamp != 0'
            . ' AND (c.start = \'\' OR c.start <= :time) AND (c.stop = \'\' OR c.stop > :time)'
            . ' ORDER BY a.sorting, c.sorting',
            [
                'page' => $pageId,
                'type' => ReaderController::TYPE,
                'ptable' => ArticleModel::getTable(),
                'time' => $time,
            ],
        );

        $controller = $this->framework->getAdapter(Controller::class);
        $contentModels = $this->framework->getAdapter(ContentModel::class);
        $articleModels = $this->framework->getAdapter(ArticleModel::class);

        $found = [];

        foreach ($rows as $row) {
            $element = $contentModels->findByPk((int) $row['content_id']);
            $article = $articleModels->findByPk((int) $row['article_id']);

            if (!$element || !$article) {
                continue;
            }

            if ($controller->isVisibleElement($element) && $controller->isVisibleElement($article)) {
                $found[] = $element;
            }
        }

        return \count($found) === 1 ? $found[0] : null;
    }

    /**
     * The page of the main request (Contao 5: a PageModel, or an id on a fragment's request), else Contao 4.13's
     * global page.
     */
    private function getPageId(): ?int
    {
        $page = $this->requestStack->getMainRequest()?->attributes->get('pageModel');

        if ($page instanceof PageModel) {
            return (int) $page->id;
        }

        if (\is_int($page) || (\is_string($page) && \ctype_digit($page))) {
            return (int) $page;
        }

        $objPage = $GLOBALS['objPage'] ?? null;

        return $objPage instanceof PageModel ? (int) $objPage->id : null;
    }
}
