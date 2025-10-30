<?php

namespace HeimrichHannot\FlareBundle\ListItemProvider;

use Contao\Controller;
use Contao\Database;
use Contao\DcaExtractor;
use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Dto\ContentContext;
use HeimrichHannot\FlareBundle\Enum\SqlEquationOperator;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterContextCollection;
use HeimrichHannot\FlareBundle\FilterElement\SimpleEquationElement;
use HeimrichHannot\FlareBundle\List\ListQueryBuilder;
use HeimrichHannot\FlareBundle\Manager\FilterContextManager;
use HeimrichHannot\FlareBundle\Manager\ListQueryManager;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use HeimrichHannot\FlareBundle\SortDescriptor\SortDescriptor;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Terminal42\DcMultilingualBundle\QueryBuilder\MultilingualQueryBuilderFactoryInterface;

class DcMultilingualListItemProvider extends AbstractListItemProvider
{
    public function __construct(
        private readonly Connection               $connection,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ListQueryManager         $listQueryManager,
        private readonly FilterContextManager $filterContextManager,

    ) {}

    protected function getConnection(): Connection
    {
        return $this->connection;
    }

    protected function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->eventDispatcher;
    }

    /**
     * @throws FilterException
     * @throws \Doctrine\DBAL\Exception
     */
    public function fetchEntries(
        ListQueryBuilder        $listQueryBuilder,
        FilterContextCollection $filters,
        ?SortDescriptor         $sortDescriptor = null,
        ?Paginator              $paginator = null,
    ): array {
        $table = $filters->getTable();
        $entries = $this->fetchEntriesOrIds(
            listQueryBuilder: $listQueryBuilder,
            filters: $filters,
            sortDescriptor: $sortDescriptor,
            paginator: $paginator,
            returnIds: false,
        );

        $entries = \array_combine(
            \array_map(
                static fn (string $id): string => \sprintf('%s.%d', $table, $id),
                \array_column($entries, 'id')
            ),
            $entries
        );

        $this->entryCache += $entries;

        return $entries;
    }

    /**
     * @throws FilterException
     * @throws \Doctrine\DBAL\Exception
     */
    public function fetchIds(
        ListQueryBuilder        $listQueryBuilder,
        FilterContextCollection $filters,
        ?SortDescriptor         $sortDescriptor = null,
        ?Paginator              $paginator = null,
    ): array {
        return $this->fetchEntriesOrIds(
            listQueryBuilder: $listQueryBuilder,
            filters: $filters,
            sortDescriptor: $sortDescriptor,
            paginator: $paginator,
            returnIds: true,
        );
    }

    /**
     * @throws FilterException
     * @throws \Doctrine\DBAL\Exception
     */
    protected function fetchEntriesOrIds(
        ListQueryBuilder        $listQueryBuilder,
        FilterContextCollection $filters,
        ?SortDescriptor         $sortDescriptor = null,
        ?Paginator              $paginator = null,
        ?bool                   $returnIds = null,
    ): array {
        $returnIds ??= false;

        $query = $this->listQueryManager->populate(
            listQueryBuilder: $listQueryBuilder,
            filters: $filters,
            order: $sortDescriptor?->toSql($this->connection->quoteIdentifier(...)),
            limit: $paginator?->getItemsPerPage() ?: null,
            offset: $paginator?->getOffset() ?: null,
            onlyId: $returnIds,
        );

        if (!$query->isAllowed())
        {
            return [];
        }

        $result = $query->execute($this->connection);

        $entries = $returnIds
            ? \array_unique($result->fetchFirstColumn())
            : $result->fetchAllAssociative();

        $result->free();

        return $entries;
    }

    /**
     * @throws FilterException
     * @throws \Doctrine\DBAL\Exception
     */
    public function fetchCount(
        ListQueryBuilder $listQueryBuilder,
        FilterContextCollection $filters,
        ContentContext $contentContext,
    ): int {

        $table = $filters->getTable();

        $onlyTranslated = (
            'translated' === $filters->getListModel()->dcmultilingual_display
            && $this->getFallbackLanguage($table) !== $GLOBALS['TL_LANGUAGE']
        );

        if ($onlyTranslated) {
            $this->applyMcQueries($listQueryBuilder, $filters, $contentContext, $GLOBALS['TL_LANGUAGE']);

            $filterDefinition = SimpleEquationElement::define(
                equationLeft: $this->getPidColumn($table),
                equationOperator: SqlEquationOperator::GREATER_THAN,
                equationRight: '0'
            );
            $filterDefinition->targetAlias = 'translation';

            $filters->add($this->filterContextManager->definitionToContext(
                $filterDefinition,
                $filters->getListModel(),
                $contentContext,
            ));
        } else {
            $filters->add($this->filterContextManager->definitionToContext(
                SimpleEquationElement::define(
                    equationLeft: $this->getPidColumn($table),
                    equationOperator: SqlEquationOperator::EQUALS,
                    equationRight: '0'
                ),
                $filters->getListModel(),
                $contentContext,
            ));

        }

        $query = $this->listQueryManager->populate(
            listQueryBuilder: $listQueryBuilder,
            filters: $filters,
            isCounting: true
        );

        if (!$query->isAllowed()) {
            return 0;
        }

        $result = $query->execute($this->connection);

        $count = $result->fetchOne() ?: 0;

        $result->free();

        return $count;
    }

    private function applyMcQueries(
        ListQueryBuilder $listQueryBuilder,
        FilterContextCollection $filters,
        ContentContext $contentContext,
        string $language,
    ): void
    {
        $table = $filters->getTable();
        $langColumnName = $this->getLangColumn($table);
        $pidColumnName = $this->getPidColumn($table);
        $regularFields = $this->getRegularFields($table);
        $translatableFields = $this->getTranslatableFields($table);

        // Always translate system columns
        $systemColumns = ['id', $langColumnName, $pidColumnName];

        foreach ($systemColumns as $field) {
            $listQueryBuilder->addRawSelect("IFNULL(translation.$field, main.$field) AS $field");
        }

        // Regular fields
        foreach (array_diff($regularFields, $translatableFields, $systemColumns) as $field) {
            $listQueryBuilder->addRawSelect("main.$field");
        }

        // Translatable fields
        foreach (array_intersect($translatableFields, $regularFields) as $field) {
            $listQueryBuilder->addRawSelect("IFNULL(translation.$field, main.$field) AS $field");
        }

        $listQueryBuilder->addJoin(
            'LEFT OUTER JOIN',
            $table,
            'translation',
            "main.id=translation.$pidColumnName AND translation.$langColumnName='$language'"
        );
        $listQueryBuilder->setTableAliasMandatory('translation');
        $listQueryBuilder->setGroupBy([]);
    }

    private function getPidColumn(string $table): string
    {
        Controller::loadDataContainer($table);
        return $GLOBALS['TL_DCA'][$table]['config']['langPid'] ?? 'langPid';
    }

    private function getLangColumn(string $table): string
    {
        Controller::loadDataContainer($table);
        return $GLOBALS['TL_DCA'][$table]['config']['langColumnName'] ?? 'language';
    }

    private function getFallbackLanguage(string $table): string|null
    {
        Controller::loadDataContainer($table);
        return $GLOBALS['TL_DCA'][$table]['config']['fallbackLang'] ?? null;
    }

    private function getRegularFields(string $table): array
    {
        $extractor = DcaExtractor::getInstance($table);
        $tableColumns = Database::getInstance()->getFieldNames($table);

        return array_intersect($tableColumns, array_keys($extractor->getFields()));
    }

    /**
     * Get the fields that are translatable.
     *
     * @return array
     */
    private function getTranslatableFields(string $table): array
    {
        Controller::loadDataContainer($table);

        $fields = [];
        $tableColumns = Database::getInstance()->getFieldNames($table);

        foreach ($GLOBALS['TL_DCA'][$table]['fields'] as $field => $data) {
            if (!isset($data['eval']['translatableFor']) || !\in_array($field, $tableColumns, true)) {
                continue;
            }

            $fields[] = $field;
        }

        return $fields;
    }
}
