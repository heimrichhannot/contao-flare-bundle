<?php

namespace HeimrichHannot\FlareBundle\EventListener\Contao;

use Composer\InstalledVersions;
use Contao\ArticleModel;
use Contao\ContentModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\Input;
use Contao\Module;
use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Contract\Config\ReaderPageMetaConfig;
use HeimrichHannot\FlareBundle\Controller\ContentElement\ReaderController;
use HeimrichHannot\FlareBundle\DataContainer\ContentContainer;
use HeimrichHannot\FlareBundle\Engine\Context\Factory\ValidationContextFactory;
use HeimrichHannot\FlareBundle\Engine\View\ValidationView;
use HeimrichHannot\FlareBundle\Manager\ReaderManager;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Registry\ProjectorRegistry;
use HeimrichHannot\FlareBundle\Specification\Factory\ListSpecificationFactory;

#[AsHook('generateBreadcrumb')]
readonly class BreadcrumbListener
{
    public function __construct(
        private Connection               $connection,
        private ListSpecificationFactory $listSpecificationFactory,
        private ReaderManager            $readerManager,
        private ProjectorRegistry        $projectorRegistry,
        private ValidationContextFactory $validationContextFactory,
    ) {}

    public function __invoke(array $items, Module $module): array
    {
        if (!$pageId = $this->tryGetReaderPageId($items)) {
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

        if (!$this->isContao5())
            // remove second to last item
        {
            unset($items[\count($items) - 2]);
        }

        $items = \array_values($items);

        if (!\count($items)) {
            return $items;
        }

        try
        {
            $autoItem ??= (
                \method_exists(Input::class, 'findGet')
                    ? Input::findGet('auto_item')
                    : Input::get('auto_item', false, true)
            );

            if (!$autoItem) {
                return $items;
            }

            $listModel = $contentModel->getRelated(ContentContainer::FIELD_LIST);

            if (!$listModel instanceof ListModel) {
                return $items;
            }

            $listSpec = $this->listSpecificationFactory->create(dataSource: $listModel);

            $validationContext = $this->validationContextFactory->createFromContent(
                contentModel: $contentModel,
                listModel: $listModel
            );

            $validationProjector = $this->projectorRegistry->getProjectorFor($listSpec, $validationContext);
            $validationView = $validationProjector->project($listSpec, $validationContext);
            \assert($validationView instanceof ValidationView, 'Expected ValidationView');

            if (!$autoItemModel = $validationView->getModelByAutoItem($autoItem)) {
                return $items;
            }

            $pageMeta = $this->readerManager->getPageMeta(new ReaderPageMetaConfig(
                contentModel: $contentModel,
                displayModel: $autoItemModel,
                listSpecification: $listSpec,
            ));

            $title = $pageMeta->getTitle();
            $item = &$items[\count($items) - 1];

            if ($title && $item)
            {
                $item['title'] = $title;
                $item['link'] = $title;
            }
        }
        catch (\Throwable)
        {
            // ignore
        }

        return $items;
    }

    public function tryGetReaderPageId(array $items): ?int
    {
        if (!\count($items) || (!$this->isContao5() && \count($items) < 2)) {
            return null;
        }

        $items = \array_values($items);
        $count = \count($items);

        $last = $items[$count - 1];
        $lastPageId = (int) ($last['data']['id'] ?? 0);

        if ($this->isContao5()) {
            return $lastPageId ?: null;
        }

        $secondLast = $items[$count - 2];
        $secondLastPageId = (int) ($secondLast['data']['id'] ?? 0);

        if (!$lastPageId || !$secondLastPageId) {
            return null;
        }

        if ($lastPageId !== $secondLastPageId) {
            return null;
        }

        return $lastPageId;
    }

    private function isContao5(): bool
    {
        if (!$contaoVersion = InstalledVersions::getVersion('contao/core-bundle')) {
            return true;
        }

        return (bool) \version_compare($contaoVersion, '5.0.0', '>=');
    }
}