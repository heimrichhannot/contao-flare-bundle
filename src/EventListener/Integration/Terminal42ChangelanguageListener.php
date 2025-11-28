<?php

namespace HeimrichHannot\FlareBundle\EventListener\Integration;

use Contao\Controller;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\PageModel;
use Doctrine\DBAL\Query\QueryBuilder;
use HeimrichHannot\FlareBundle\Event\ListViewDetailsPageUrlGeneratedEvent;
use HeimrichHannot\FlareBundle\Manager\RequestManager;
use HeimrichHannot\FlareBundle\Util\DcMultilingualHelper;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Terminal42\ChangeLanguage\Event\ChangelanguageNavigationEvent;
use Terminal42\ChangeLanguage\PageFinder;
use Terminal42\DcMultilingualBundle\Driver;
use Terminal42\DcMultilingualBundle\QueryBuilder\MultilingualQueryBuilderFactoryInterface;

class Terminal42ChangelanguageListener
{
    private ?MultilingualQueryBuilderFactoryInterface $queryBuilderFactory;
    private ?PageFinder $pageFinder;
    private array $pageFinderCache = [];
    private string $language;

    public function __construct(
        private readonly RequestManager $requestManager,
        private readonly RequestStack   $requestStack,
    ) {}

    /**
     * Called by the Dependency Injection container to inject the factory.
     */
    public function setMultilingualQueryBuilderFactory(
        ?MultilingualQueryBuilderFactoryInterface $queryBuilderFactory,
    ): void {
        $this->queryBuilderFactory = $queryBuilderFactory;
    }
    
    #[AsEventListener(priority: 220)]
    public function onListViewDetailsPageUrlGenerated(ListViewDetailsPageUrlGeneratedEvent $event): void
    {
        $eventPage = $event->getPage();

        if (!$langPage = $this->findPageForLanguage($eventPage)) {
            return;
        }

        /** @noinspection PhpCastIsUnnecessaryInspection */
        if ((int) $langPage->id === (int) $eventPage->id) {
            return;
        }

        $url = $langPage->getAbsoluteUrl('/' . $event->getAutoItem());

        $event->setPage($langPage);
        $event->setUrl($url);
    }

    private function findPageForLanguage(PageModel $page): ?PageModel
    {
        $lang = $this->getLanguage();

        $key = "{$lang}.{$page->id}";

        if (!isset($this->pageFinderCache[$key]))
        {
            $this->pageFinderCache[$key] = $this->getPageFinder()?->findAssociatedForLanguage($page, $lang);
        }

        return $this->pageFinderCache[$key];
    }

    private function getLanguage(): string
    {
        return $this->language ??= (
            $GLOBALS['TL_LANGUAGE']
            ?? throw new \RuntimeException('TL_LANGUAGE is not set.')
        );
    }

    private function getPageFinder(): ?PageFinder
    {
        if (!isset($this->pageFinder))
        {
            $this->pageFinder = \class_exists(PageFinder::class) ? new PageFinder() : null;
        }

        return $this->pageFinder;
    }

    #[AsHook('changelanguageNavigation')]
    public function onChangeLanguageNavigation(ChangelanguageNavigationEvent $event): void
    {
        if (!isset($this->queryBuilderFactory)) {
            return;
        }

        if ($event->getNavigationItem()->isCurrentPage()) {
            return;
        }

        if (!$request = $this->requestStack->getCurrentRequest()) {
            return;
        }

        if (!$attribute = $this->requestManager->getReader()) {
            return;
        }

        $table = $attribute->getModel()::getTable();
        $listModel = $attribute->getListModel();

        if ($listModel->dc !== $table) {
            return;
        }

        Controller::loadDataContainer($table);

        $dcDriver = $GLOBALS['TL_DCA'][$table]['config']['dataContainer'] ?? null;
        if ($dcDriver !== Driver::class) {
            return;
        }

        $aliasColumnName = $listModel->getAutoItemField();

        $row = $this->createQueryBuilder($table, $this->getLanguage())
            ->andWhere("($table.$aliasColumnName = :autoitem OR translation.$aliasColumnName = :autoitem)")
            ->setParameter('autoitem', $request->attributes->get('auto_item'))
            ->executeQuery()
            ->fetchAssociative();

        if (false === $row) {
            return;
        }

        $pidColumn = DcMultilingualHelper::getPidColumn($table);
        $id = $row[$pidColumn] > 0 ? $row[$pidColumn] : $row['id'];

        $targetRoot = $event->getNavigationItem()->getRootPage();
        $language = $targetRoot->rootLanguage;

        $fallbackLang = DcMultilingualHelper::getFallbackLanguage($table);
        if (null !== $fallbackLang && $fallbackLang === $language) {
            $language = '';
        }

        $translated = $this->createQueryBuilder($table, $language)
            ->andWhere("$table.id = :id OR translation.langPid = :id")
            ->setParameter('id', $id)
            ->executeQuery()->fetchAssociative();

        if (false === $translated) {
            return;
        }

        $event->getUrlParameterBag()->setUrlAttribute('auto_item', $translated['alias'] ?? false);
    }

    private function createQueryBuilder(string $table, string $language): QueryBuilder
    {
        return $this->queryBuilderFactory->build(
            $table,
            DcMultilingualHelper::getPidColumn($table),
            DcMultilingualHelper::getLangColumn($table),
            DcMultilingualHelper::getRegularFields($table),
            DcMultilingualHelper::getTranslatableFields($table)
        )->buildQueryBuilderForFind($language);
    }
}