<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contao\Reader;

use Contao\ArticleModel;
use Contao\ContentModel;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\Model;
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
        $sql = <<<'SQL'
            SELECT c.id AS content_id, a.id AS article_id
              FROM tl_content c
             INNER JOIN tl_article a ON a.id = c.pid
             WHERE a.pid = :page AND c.type = :type AND c.ptable IN (:ptable, '')
               AND a.published = 1 AND (a.start = '' OR a.start <= :time) AND (a.stop = '' OR a.stop > :time)
               AND c.invisible = 0 AND c.tstamp != 0
               AND (c.start = '' OR c.start <= :time) AND (c.stop = '' OR c.stop > :time)
             ORDER BY a.sorting, c.sorting
            SQL;
        $params = [
            'page' => $pageId,
            'type' => ReaderController::TYPE,
            'ptable' => ArticleModel::getTable(),
            'time' => $time,
        ];
        $rows = $this->connection->fetchAllAssociative($sql, $params);

        if ($rows === []) {
            return null;
        }

        // Models, not rows: Controller::isVisibleElement() and its hook take models. One query per table at most, the
        // registry's instances where they exist.
        $contentAdapter = $this->framework->getAdapter(ContentModel::class);
        $articleAdapter = $this->framework->getAdapter(ArticleModel::class);
        $elementIds = \array_map(\intval(...), \array_column($rows, 'content_id'));
        $articleIds = \array_values(\array_unique(\array_map(\intval(...), \array_column($rows, 'article_id'))));
        $elementModels = $contentAdapter->findMultipleByIds($elementIds);
        $articleModels = $articleAdapter->findMultipleByIds($articleIds);
        $elements = self::byId($elementModels);
        $articles = self::byId($articleModels);

        $controller = $this->framework->getAdapter(Controller::class);
        $visibleArticles = [];
        $found = null;

        foreach ($rows as $row) {
            $articleId = (int) $row['article_id'];
            $element = $elements[(int) $row['content_id']] ?? null;
            $article = $articles[$articleId] ?? null;

            if (!$element || !$article) {
                continue;
            }

            $visibleArticles[$articleId] ??= (bool) $controller->isVisibleElement($article);

            if (!$visibleArticles[$articleId] || !$controller->isVisibleElement($element)) {
                continue;
            }

            if ($found !== null) {
                // a second visible reader: none is the page's reader
                return null;
            }

            $found = $element;
        }

        return $found;
    }

    /**
     * @template T of Model
     * @param iterable<T>|null $models
     * @return array<int, T>
     */
    private static function byId(?iterable $models): array
    {
        $byId = [];

        foreach ($models ?? [] as $model) {
            $byId[(int) $model->id] = $model;
        }

        return $byId;
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

        if (\is_int($page) || \is_string($page)) {
            $id = \filter_var($page, \FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

            if ($id !== false) {
                return $id;
            }
        }

        $objPage = $GLOBALS['objPage'] ?? null;

        return $objPage instanceof PageModel ? (int) $objPage->id : null;
    }
}
