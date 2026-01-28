<?php

namespace HeimrichHannot\FlareBundle\EventListener\Integration;

use Contao\Controller;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\PageModel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use HeimrichHannot\FlareBundle\Enum\SqlEquationOperator;
use HeimrichHannot\FlareBundle\Event\AbstractFetchEvent;
use HeimrichHannot\FlareBundle\Event\FetchAutoItemEvent;
use HeimrichHannot\FlareBundle\Event\FetchListEntriesEvent;
use HeimrichHannot\FlareBundle\Event\ListViewDetailsPageUrlGeneratedEvent;
use HeimrichHannot\FlareBundle\Event\FetchCountEvent;
use HeimrichHannot\FlareBundle\FilterElement\SimpleEquationElement;
use HeimrichHannot\FlareBundle\List\ListQueryBuilder;
use HeimrichHannot\FlareBundle\ListType\DcMultilingualListType;
use HeimrichHannot\FlareBundle\Manager\FilterContextManager;
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

    public function __construct(
        private readonly Connection           $connection,
        private readonly FilterContextManager $filterContextManager,
        private readonly RequestManager       $requestManager,
        private readonly RequestStack         $requestStack,
    ) {}

    /**
     * Called by the Dependency Injection container to inject the factory.
     */
    public function setMultilingualQueryBuilderFactory(
        ?MultilingualQueryBuilderFactoryInterface $queryBuilderFactory,
    ): void {
        $this->queryBuilderFactory = $queryBuilderFactory;
    }

    #[AsEventListener]
    public function fetchAutoItem(FetchAutoItemEvent $event): void
    {
        $list = $event->getListSpecification();

        if ($list->type !== DcMultilingualListType::TYPE) {
            return;
        }

        if ($this->isFallbackLanguage($event)) {
            return;
        }

        $table = $list->dc;

        $this->applyMlQueriesIfNecessary(
            $event->getListQueryBuilder(),
            $table,
            DcMultilingualHelper::getLanguage(),
        );

        if (!$autoItemFilterContext = $event->getAutoItemFilterContext()) {
            return;
        }

        // use the translated alias for auto_item retrieval if the alias field is translatable
        $translatableFields = DcMultilingualHelper::getTranslatableFields($table);

        if (!\in_array($list->getAutoItemField(), $translatableFields, true)) {
            return;
        }

        // todo: probably should not set entire service as targeted

        $autoItemFilterContext->getDescriptor()->setIsTargeted(true);
        $autoItemFilterContext->getFilterModel()->targetAlias = 'translation';
    }

    #[AsEventListener('flare.list.' . DcMultilingualListType::TYPE . '.fetch_entries')]
    public function fetchEntries(FetchListEntriesEvent $event): void
    {
        if ($this->isFallbackLanguage($event)) {
            return;
        }

        $this->applyMlQueriesIfNecessary(
            $event->getListQueryBuilder(),
            $event->getFilters(),
            DcMultilingualHelper::getLanguage(),
        );
    }

    private function isFallbackLanguage(AbstractFetchEvent $event): bool
    {
        $filters = $event->getFilters();
        $table = $filters->getTable();
        $lang = DcMultilingualHelper::getLanguage();
        $langFallback = DcMultilingualHelper::getLanguageFallback($table);

        return $lang === $langFallback;
    }
    
    #[AsEventListener('flare.list.' . DcMultilingualListType::TYPE . '.fetch_count')]
    public function listViewFetchCountEvent(FetchCountEvent $event): void
    {
        $filters = $event->getFilters();
        $table = $filters->getTable();
        $lang = DcMultilingualHelper::getLanguage();
        $langFallback = DcMultilingualHelper::getLanguageFallback($table);

        $this->applyMlQueriesIfNecessary($event->getListQueryBuilder(), $filters, $lang);

        $contentContext = $event->getContentContext();
        
        $dcMultilingualDisplay = $event->getContentContext()->getContentModel()->flare_dcMultilingualDisplay
            ?: $filters->getListModel()->dcMultilingual_display;

        if ($lang !== $langFallback && $dcMultilingualDisplay === DcMultilingualHelper::DISPLAY_LOCALIZED)
            // localized list view
        {
            $filterDefinition = SimpleEquationElement::define(
                equationLeft: DcMultilingualHelper::getPidColumn($table),
                equationOperator: SqlEquationOperator::GREATER_THAN,
                equationRight: '0'
            );
            $filterDefinition->targetAlias = 'translation';
        }
        else
        {
            $filterDefinition = SimpleEquationElement::define(
                equationLeft: DcMultilingualHelper::getPidColumn($table),
                equationOperator: SqlEquationOperator::EQUALS,
                equationRight: '0'
            );
        }

        $filters->add($this->filterContextManager->definitionToContext(
            definition: $filterDefinition,
            listModel: $filters->getListModel(),
            contentContext: $contentContext,
        ));
    }

    private function applyMlQueriesIfNecessary(
        ListQueryBuilder $listQueryBuilder,
        string $table,
        string $language,
    ): void
    {
        if (in_array('translation', $listQueryBuilder->getMandatoryTableAliases(), true)) {
            return;
        }

        $langColumnName = DcMultilingualHelper::getLangColumn($table);
        $pidColumnName = DcMultilingualHelper::getPidColumn($table);
        $regularFields = DcMultilingualHelper::getRegularFields($table);
        $translatableFields = DcMultilingualHelper::getTranslatableFields($table);

        // Always translate system columns
        $systemColumns = ['id', $langColumnName, $pidColumnName];

        $qMain = $listQueryBuilder->getMainAlias(quoted: true);

        foreach ($systemColumns as $field) {
            $listQueryBuilder->addRawSelect("IFNULL(translation.$field, $qMain.$field) AS $field");
        }

        // Regular fields
        foreach (array_diff($regularFields, $translatableFields, $systemColumns) as $field) {
            $listQueryBuilder->addRawSelect("$qMain.$field");
        }

        // Translatable fields
        foreach (array_intersect($translatableFields, $regularFields) as $field) {
            $listQueryBuilder->addRawSelect("IFNULL(translation.$field, $qMain.$field) AS $field");
        }

        $listQueryBuilder->addJoin(
            'LEFT OUTER JOIN',
            $table,
            'translation',
            "$qMain.id=translation.$pidColumnName AND translation.$langColumnName='$language'"
        );
        $listQueryBuilder->setTableAliasMandatory('translation');
        $listQueryBuilder->setGroupBy([]);
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
        $lang = DcMultilingualHelper::getLanguage();

        $key = "{$lang}.{$page->id}";

        if (!isset($this->pageFinderCache[$key]))
        {
            $this->pageFinderCache[$key] = $this->getPageFinder()?->findAssociatedForLanguage($page, $lang);
        }

        return $this->pageFinderCache[$key];
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

        if (!$reader = $this->requestManager->getReader()) {
            return;
        }

        $table = $reader->getModel()::getTable();
        $listModel = $reader->getListModel();

        if ($listModel->dc !== $table) {
            return;
        }

        Controller::loadDataContainer($table);

        $dcDriver = $GLOBALS['TL_DCA'][$table]['config']['dataContainer'] ?? null;
        if ($dcDriver !== Driver::class) {
            return;
        }

        $aliasColumnName = $listModel->getAutoItemField();
        $qTable = $this->connection->quoteIdentifier($table);
        $qAliasColumnName = $this->connection->quoteIdentifier($aliasColumnName);

        $lang = DcMultilingualHelper::getLanguage();

        $row = $this->createQueryBuilder($table, $lang)
            ->andWhere("({$qTable}.{$qAliasColumnName} = :autoitem OR translation.{$qAliasColumnName} = :autoitem)")
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

        $fallbackLang = DcMultilingualHelper::getLanguageFallback($table);
        if (null !== $fallbackLang && $fallbackLang === $language) {
            $language = '';
        }

        $qPidColumn = $this->connection->quoteIdentifier($pidColumn);

        $translated = $this->createQueryBuilder($table, $language)
            ->andWhere("{$qTable}.id = :id OR translation.{$qPidColumn} = :id")
            ->setParameter('id', $id)
            ->executeQuery()
            ->fetchAssociative();

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