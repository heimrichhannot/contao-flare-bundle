<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\EventListener\Contao;

use Contao\ArticleModel;
use Contao\ContentModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\Input;
use Contao\Module;
use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Controller\ContentElement\ReaderController;
use HeimrichHannot\FlareBundle\DataContainer\ContentContainer;
use HeimrichHannot\FlareBundle\Engine\Context\Factory\ValidationContextFactory;
use HeimrichHannot\FlareBundle\Engine\View\ValidationView;
use HeimrichHannot\FlareBundle\Event\ReaderPageMetaEvent;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Registry\ProjectorRegistry;
use HeimrichHannot\FlareBundle\Specification\Factory\ListSpecificationFactory;
use HeimrichHannot\FlareBundle\Util\Env;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsHook('generateBreadcrumb')]
readonly class BreadcrumbListener
{
    public function __construct(
        private Connection               $connection,
        private EventDispatcherInterface $eventDispatcher,
        private ListSpecificationFactory $listSpecificationFactory,
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

        if (Env::isContao4())
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

            /** @var ReaderPageMetaEvent $pageMetaEvent */
            $pageMetaEvent = $this->eventDispatcher->dispatch(new ReaderPageMetaEvent(
                contentModel: $contentModel,
                displayModel: $autoItemModel,
                listSpecification: $listSpec,
            ));

            $title = $pageMetaEvent->getPageMeta()?->getTitle();
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
        $isContao4 = Env::isContao4();
        if (!\count($items) || ($isContao4 && \count($items) < 2)) {
            return null;
        }

        $items = \array_values($items);
        $count = \count($items);

        $last = $items[$count - 1];
        $lastPageId = (int) ($last['data']['id'] ?? 0);

        if (!$isContao4) {
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
}