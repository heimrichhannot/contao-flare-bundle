<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\FilterElement;

use Contao\Controller;
use Contao\DataContainer;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Contract\FilterElement\HydrateFormContract;
use HeimrichHannot\FlareBundle\Contract\FilterElement\IntrinsicValueContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterCallback;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Engine\Context\ValidationContext;
use HeimrichHannot\FlareBundle\Event\FilterElementFormTypeOptionsEvent;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterInvocation;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilder;
use HeimrichHannot\FlareBundle\Form\Factory\ChoicesBuilderFactory;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Query\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Specification\FilterDefinition;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormInterface;

#[AsFilterElement(
    type: self::TYPE,
    palette: '{filter_legend},fieldGeneric,isMultiple,isExpanded,preselect',
    formType: ChoiceType::class,
)]
class FieldValueChoiceElement extends AbstractFilterElement implements HydrateFormContract, IntrinsicValueContract
{
    public const TYPE = 'flare_fieldValueChoice';

    private array $foreignValueCache = [];
    private array $localValueCache = [];

    public function __construct(
        private readonly Connection $connection,
        private readonly ChoicesBuilderFactory $choicesBuilderFactory,
    ) {}

    /**
     * @throws FilterException
     */
    public function __invoke(FilterInvocation $inv, FilterQueryBuilder $qb): void
    {
        if ($inv->context instanceof ValidationContext) {
            return;
        }

        if (!($field = $inv->filter->fieldGeneric)) {
            return;
        }

        if (!$value = $inv->getValue()) {
            return;
        }

        $colField = $qb->column($field);

        if (\count($value) < 2)
        {
            $qb->where("LOWER(TRIM({$colField})) = :value")
                ->setParameter('value', \reset($value));
        }
        /** @mago-expect lint:no-else-clause This else clause is fine. */
        else
        {
            $qb->where("LOWER(TRIM({$colField})) IN (:values)")
                ->setParameter('values', $value);
        }
    }

    public function processRuntimeValue(mixed $value, ListSpecification $list, FilterDefinition $filter): ?array
    {
        return $this->extractSubmittedData((array) $value);
    }

    public function getIntrinsicValue(ListSpecification $list, FilterDefinition $filter): ?array
    {
        return $this->extractPreselectData($filter);
    }

    public function extractFormData(FormInterface $form): mixed
    {
        return $form->getViewData();
    }

    public function extractPreselectData(FilterDefinition $filter): ?array
    {
        if (!$preselect = $filter->preselect) {
            return null;
        }

        if (\is_array($preselect)) {
            return $preselect;
        }

        if ($filter->isMultiple
            || (\is_string($preselect) && \preg_match('/^a:\d+:\{.*}$/', $preselect)))
        {
            return StringUtil::deserialize($preselect, true);
        }

        return [$preselect];
    }

    public function extractSubmittedData(array $submittedData): ?array
    {
        $submittedData = \array_filter($submittedData);
        $submittedData = \array_map('strtolower', \array_map('trim', $submittedData));
        $submittedData = \array_filter(
            $submittedData,
            static fn(string $value): bool => $value !== '' && $value !== ChoicesBuilder::EMPTY_CHOICE,
        );

        return $submittedData ?: null;
    }

    public function hydrateForm(FormInterface $field, ListSpecification $list, FilterDefinition $filter): void
    {
        if ($field->isSubmitted()) {
            return;
        }

        if (!$preselect = $this->extractPreselectData($filter)) {
            return;
        }

        $choices = $field->getConfig()->getOption('choices') ?? [];

        $data = [];
        foreach ($preselect as $alias) {
            if ($choice = $choices[$alias] ?? null) {
                $data[] = $choice;
            }
        }

        if (!$filter->isMultiple) {
            $data = \reset($data);
        }

        $field->setData($data);
    }

    public function handleFormTypeOptions(FilterElementFormTypeOptionsEvent $event): void
    {
        $choices = $event->choicesBuilder
            ->enable()
            ->setEmptyOption(!$event->filter->isMultiple);

        $table = $event->list->dc;
        $field = $event->filter->fieldGeneric ?: '';

        if (!\is_null($foreignValues = $this->getForeignValues($table, $field)))
        {
            // TODO: display of frontend form values should be configurable
            foreach ($foreignValues as $id => $label) {
                $choices->add((string) $id, (string) $label, $id);
            }
        }
        /** @mago-expect lint:no-else-clause This else clause is fine. */
        else
        {
            foreach ($this->getLocalValues($table, $field) as $value) {
                $choices->add((string) $value, (string) $value, $value);
            }
        }

        $event->options['multiple'] = (bool) $event->filter->isMultiple;
        $event->options['expanded'] = (bool) $event->filter->isExpanded;
        $event->options['required'] = false;
    }

    #[AsFilterCallback(self::TYPE, 'fields.isMultiple.load')]
    #[AsFilterCallback(self::TYPE, 'fields.isExpanded.load')]
    public function onLoad_isMultiple(
        mixed          $value,
        ?DataContainer $dc,
        FilterModel    $filterModel,
        ListModel $listModel
    ): mixed {
        if (!$dc || !($dcTable = $dc->table) || !($dcField = $dc->field)) {
            return $value;
        }

        $dca = &$GLOBALS['TL_DCA'][$dcTable]['fields'][$dcField];
        $dca['eval']['submitOnChange'] = $dcField === 'isMultiple';
        $dca['eval']['tl_class'] = 'cbx m12 w25';

        return $value;
    }

    #[AsFilterCallback(self::TYPE, 'fields.preselect.load')]
    public function onLoad_preselect(
        mixed          $value,
        ?DataContainer $dc,
        FilterModel    $filterModel,
        ListModel $listModel
    ): mixed {
        if (!$dc
            || !($dcTable = $dc->table)
            || !($dcField = $dc->field)
            || !($table = $listModel->dc)
            || !($valueField = $filterModel->fieldGeneric))
        {
            return $value;
        }

        $flareDca = &$GLOBALS['TL_DCA'][$dcTable]['fields'][$dcField];

        $choices = $this->choicesBuilderFactory
            ->createChoicesBuilder()
            ->setModelSuffix('[%id%]')
            ->enable();

        Controller::loadDataContainer($table);

        if (!\is_null($foreignValues = $this->getForeignValues($table, $valueField)))
        {
            foreach ($foreignValues as $id => $label) {
                $choices->add((string) $id, (string) $label, $id);
            }
        }
        /** @mago-expect lint:no-else-clause This else clause is fine. */
        else
        {
            foreach ($this->getLocalValues($table, $valueField) as $option) {
                $choices->add((string) $option, (string) $option, $option);
            }
        }

        $flareDca['inputType'] = 'select';
        $flareDca['eval']['multiple'] = $filterModel->isMultiple;
        $flareDca['eval']['chosen'] = true;
        $flareDca['eval']['includeBlankOption'] = true;
        $flareDca['options_callback'] = static fn (DataContainer $dc): array => $choices->buildOptions();

        return $value;
    }

    private function getForeignValues(string $table, string $field): ?array
    {
        if (isset($this->foreignValueCache[$table][$field])) {
            return $this->foreignValueCache[$table][$field];
        }

        $dca = $GLOBALS['TL_DCA'][$table]['fields'][$field] ?? [];

        if (!$foreignKey = $dca['foreignKey'] ?? null) {
            return null;
        }

        [$foreignTable, $foreignDisplayColumn] = \explode('.', $foreignKey, 2);

        if (!$foreignTable || !$foreignDisplayColumn) {
            return null;
        }

        $foreignTable = $this->connection->quoteIdentifier($foreignTable);
        $foreignDisplayColumn = $this->connection->quoteIdentifier($foreignDisplayColumn);
        $foreignField = $this->connection->quoteIdentifier($dca['relation']['field'] ?? 'id');

        // The string-concatenation happens directly in SQL, producing a key-value pair for each option in the format:
        //   `{id} => "{value} [{id}]"` (where `{value}` is the display column value, e.g., `tl_user.name`)
        $sql = <<<SQL
            SELECT {$foreignField} AS `id`,
                   CONCAT({$foreignDisplayColumn}, ' [', {$foreignField}, ']') AS `label`
              FROM {$foreignTable}
             WHERE `tstamp` > 0
             ORDER BY `label`
        SQL;

        return $this->foreignValueCache[$table][$field] = $this->connection->fetchAllKeyValue($sql);
    }

    private function getLocalValues(string $table, string $field): array
    {
        if (isset($this->localValueCache[$table][$field])) {
            return $this->localValueCache[$table][$field];
        }

        if (!$field || !$table) {
            return [];
        }

        $qTable = $this->connection->quoteIdentifier($table);
        $qField = $this->connection->quoteIdentifier($field);

        $sql = <<<SQL
            SELECT DISTINCT
                CAST({$qField} AS CHAR) AS `value`
              FROM {$qTable}
             WHERE {$qField} IS NOT NULL
              AND `tstamp` > 0
             ORDER BY `value`;
        SQL;

        $values = \array_values(\array_filter(
            $this->connection->fetchFirstColumn($sql),
            static fn (mixed $v): bool => (!\is_string($v) || \trim($v) !== '')
        ));

        return $this->localValueCache[$table][$field] = $values;
    }
}
