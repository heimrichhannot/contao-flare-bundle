<?php

namespace HeimrichHannot\FlareBundle\EventListener;

use Composer\InstalledVersions;
use Contao\Controller;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Doctrine\DBAL\Query\QueryBuilder;
use HeimrichHannot\FlareBundle\Event\CreateListViewBuilderEvent;
use HeimrichHannot\FlareBundle\ListView\Resolver\MultilingualListViewResolver;
use HeimrichHannot\FlareBundle\Manager\ListViewManager;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Util\DcMultilingualHelper;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Terminal42\ChangeLanguage\Event\ChangelanguageNavigationEvent;
use Terminal42\DcMultilingualBundle\Driver;
use Terminal42\DcMultilingualBundle\QueryBuilder\MultilingualQueryBuilderFactoryInterface;

class MultilingualListener
{
    private MultilingualQueryBuilderFactoryInterface $queryBuilderFactory;

    public function __construct(
        private readonly ListViewManager $listViewManager,
        private readonly RequestStack $requestStack,
    ) {
    }

    #[AsEventListener]
    public function onCreateListViewBuilderEvent(CreateListViewBuilderEvent $event): void
    {
        if (!InstalledVersions::isInstalled('terminal42/contao-changelanguage')) {
            return;
        }

        $event->setResolver(new MultilingualListViewResolver($this->listViewManager));
    }

    #[AsHook('changelanguageNavigation')]
    public function onChangelanguageNavigation(ChangelanguageNavigationEvent $event): void
    {
        if (!isset($this->queryBuilderFactory)) {
            return;
        }

        if ($event->getNavigationItem()->isCurrentPage()) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (!($attributes = $request->attributes->get('flare_reader'))) {
            return;
        }

        $table = $attributes['table'];
        $listModel = ListModel::findByPk((int)$attributes['list_id']);
        if (!$listModel || $listModel->dc !== $table) {
            return;
        }

        Controller::loadDataContainer($table);
        if (($GLOBALS['TL_DCA'][$table]['config']['dataContainer'] ?? '') !== Driver::class) {
            return;
        }

        $aliasColumnName = $listModel->getAutoItemField();
        $row = $this->createQueryBuilder($table, $GLOBALS['TL_LANGUAGE'])
            ->andWhere("($table.$aliasColumnName = :autoitem OR translation.$aliasColumnName = :autoitem)")
            ->setParameter('autoitem', $request->attributes->get('auto_item'))
            ->executeQuery()->fetchAssociative();

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

    public function setFactory(MultilingualQueryBuilderFactoryInterface $queryBuilderFactory): void
    {
        $this->queryBuilderFactory = $queryBuilderFactory;
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