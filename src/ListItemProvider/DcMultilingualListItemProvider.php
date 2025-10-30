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
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class DcMultilingualListItemProvider extends ListItemProvider
{
    public function __construct(
        Connection               $connection,
        EventDispatcherInterface $eventDispatcher,
        ListQueryManager         $listQueryManager,
        private readonly FilterContextManager $filterContextManager,
    ) {
        parent::__construct($connection, $eventDispatcher, $listQueryManager);
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

        $this->applyMlQueries($listQueryBuilder, $filters, $contentContext, $GLOBALS['TL_LANGUAGE']);

        if ($onlyTranslated) {
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

        return parent::fetchCount($listQueryBuilder, $filters, $contentContext);
    }

    private function applyMlQueries(
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
