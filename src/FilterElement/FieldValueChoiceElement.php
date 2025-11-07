<?php

namespace HeimrichHannot\FlareBundle\FilterElement;

use Contao\DataContainer;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Contract\FilterElement\HydrateFormContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterCallback;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilder;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilderFactory;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormInterface;

#[AsFilterElement(
    alias: FieldValueChoiceElement::TYPE,
    palette: '{filter_legend},fieldGeneric,isMultiple,preselect',
    formType: ChoiceType::class,
)]
class FieldValueChoiceElement extends AbstractFilterElement implements HydrateFormContract
{
    public const TYPE = 'flare_fieldValueChoice';

    private array $valueCache = [];

    public function __construct(
        private readonly Connection $connection,
        private readonly ChoicesBuilderFactory $choicesBuilderFactory,
    ) {}

    /**
     * @throws FilterException
     */
    public function __invoke(FilterContext $context, FilterQueryBuilder $qb): void
    {
        if ($context->getContentContext()->isReader()) {
            return;
        }

        if (!($field = $context->getFilterModel()->fieldGeneric)) {
            return;
        }

        $submittedData = \array_filter((array) $context->getSubmittedData());
        $submittedData = \array_map('strtolower', \array_map('trim', $submittedData));
        $submittedData = \array_filter(
            $submittedData,
            static fn(string $value): bool => $value !== '' && $value !== ChoicesBuilder::EMPTY_CHOICE,
        );

        if (!\count($submittedData)) {
            return;
        }

        $colField = $qb->column($field);

        if (\count($submittedData) < 2)
        {
            $qb->where("LOWER(TRIM({$colField})) = :value")
                ->setParameter('value', \reset($submittedData));
        }
        /** @mago-expect lint:no-else-clause This else clause is fine. */
        else
        {
            $qb->where("LOWER(TRIM({$colField})) IN (:values)")
                ->setParameter('values', $submittedData);
        }
    }

    public function hydrateForm(FilterContext $context, FormInterface $field): void
    {
        $filterModel = $context->getFilterModel();

        if (!$preselect = $filterModel->preselect) {
            return;
        }

        if ($filterModel->isMultiple)
        {
            $preselect = StringUtil::deserialize($preselect, true);

        }

        $field->setData($preselect);
    }

    public function getFormTypeOptions(FilterContext $context, ChoicesBuilder $choices): array
    {
        $filterModel = $context->getFilterModel();
        $choices->enable()->setEmptyOption(!$filterModel->isMultiple);

        $table = $context->getTable();
        $field = $filterModel->fieldGeneric ?: '';

        $values = $this->getDistinctValues($table, $field);

        foreach ($values as $value) {
            $choices->add((string) $value, (string) $value);
        }

        return [
            'multiple' => (bool) $context->getFilterModel()->isMultiple,
            'required' => false,
        ];
    }

    #[AsFilterCallback(self::TYPE, 'fields.isMultiple.load')]
    public function onLoad_isMultiple(
        mixed          $value,
        ?DataContainer $dc,
        FilterModel    $filterModel,
        ListModel      $listModel
    ): mixed {
        if (!$dc || !($dcTable = $dc->table) || !($dcField = $dc->field)) {
            return $value;
        }

        $dca = &$GLOBALS['TL_DCA'][$dcTable]['fields'][$dcField];
        $dca['eval']['submitOnChange'] = true;

        return $value;
    }

    #[AsFilterCallback(self::TYPE, 'fields.preselect.load')]
    public function onLoad_preselect(
        mixed          $value,
        ?DataContainer $dc,
        FilterModel    $filterModel,
        ListModel      $listModel
    ): mixed {
        if (!$dc
            || !($dcTable = $dc->table)
            || !($dcField = $dc->field)
            || !($table = $listModel->dc)
            || !($valueField = $filterModel->fieldGeneric))
        {
            return $value;
        }

        $dca = &$GLOBALS['TL_DCA'][$dcTable]['fields'][$dcField];

        $choices = $this->choicesBuilderFactory
            ->createChoicesBuilder()
            ->setModelSuffix('[%id%]')
            ->enable();

        $dca['inputType'] = 'select';
        $dca['eval']['multiple'] = $filterModel->isMultiple;
        $dca['eval']['chosen'] = true;
        $dca['eval']['includeBlankOption'] = true;
        $dca['options_callback'] = static fn(DataContainer $dc): array => $choices->buildOptions();

        foreach ($this->getDistinctValues($table, $valueField) as $option) {
            $choices->add($option, $option);
        }

        return $value;
    }

    private function getDistinctValues(string $table, string $field): array
    {
        if (isset($this->valueCache[$table][$field])) {
            return $this->valueCache[$table][$field];
        }

        if (!$field || !$table) {
            return [];
        }

        $table = $this->connection->quoteIdentifier($table);
        $field = $this->connection->quoteIdentifier($field);

        $result = $this->connection
            ->prepare("SELECT DISTINCT {$field} AS value FROM {$table} WHERE {$field} IS NOT NULL ORDER BY {$field}")
            ->executeQuery();

        $values = $result->fetchFirstColumn();

        return $this->valueCache[$table][$field] = \array_filter($values);
    }
}