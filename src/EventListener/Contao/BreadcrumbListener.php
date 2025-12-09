<?php

namespace HeimrichHannot\FlareBundle\EventListener\Contao;

use Contao\ArticleModel;
use Contao\ContentModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\Module;
use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Contract\Config\ReaderPageMetaConfig;
use HeimrichHannot\FlareBundle\Controller\ContentElement\ReaderController;
use HeimrichHannot\FlareBundle\Manager\ReaderManager;

#[AsHook('generateBreadcrumb')]
readonly class BreadcrumbListener
{
    public function __construct(
        private Connection    $connection,
        private ReaderManager $readerManager,
    ) {}

    public function __invoke(array $items, Module $module): array
    {
        if (!$pageId = $this->tryGetFlareReaderPageId($items)) {
            return $items;
        }

        $qArticle = $this->connection->quoteIdentifier(ArticleModel::getTable());
        $qId = $this->connection->quoteIdentifier('id');
        $qPid = $this->connection->quoteIdentifier('pid');

        $articleIds = $this->connection
            ->prepare("SELECT DISTINCT({$qId}) AS {$qId} FROM {$qArticle} WHERE {$qPid} = :pid LIMIT 1")
            ->executeQuery(['pid' => $pageId])
            ->fetchFirstColumn();

        if (!$articleIds) {
            return $items;
        }

        $articleIds = \array_map('\intval', $articleIds);

        $contentModel = ContentModel::findOneBy(
            ['ptable=? AND pid IN (?) AND type=?'],
            [ArticleModel::getTable(), \implode(',', $articleIds), ReaderController::TYPE],
        );

        if (!$contentModel) {
            return $items;
        }

        // remove second to last item
        unset($items[\count($items) - 2]);

        $items = \array_values($items);

        try
        {
            $content = $this->readerManager->evalContent($contentModel);
            $pageMeta = $this->readerManager->getPageMeta(new ReaderPageMetaConfig(
                listModel: $content->listModel,
                model: $content->model,
                contentContext: $content->contentContext,
                contentModel: $contentModel,
            ));

            $title = $pageMeta->getTitle();
            $item = &$items[\count($items) - 1];

            if ($title && $item)
            {
                $item['title'] = $title;
                $item['link'] = $title;
            }
        }
        catch (\Throwable $exception)
        {
            // ignore
        }

        return $items;
    }

    public function tryGetFlareReaderPageId(array $items): ?int
    {
        if (\count($items) < 2) {
            return null;
        }

        $items = \array_values($items);
        $count = \count($items);

        $last = $items[$count - 1];
        $secondLast = $items[$count - 2];

        $lastPageId = (int) ($last['data']['id'] ?? 0);
        $secondLastPageId = (int) ($secondLast['data']['id'] ?? 0);

        if (!$lastPageId || !$secondLastPageId) {
            return null;
        }

        if ($lastPageId !== $secondLastPageId) {
            return null;
        }

        return $lastPageId;
    }
}