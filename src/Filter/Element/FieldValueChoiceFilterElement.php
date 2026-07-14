<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Element;

use Contao\Controller;
use Contao\DataContainer;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Engine\Context\ValidationContext;
use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\Type\FieldValueChoiceFilterType;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilder;
use HeimrichHannot\FlareBundle\Form\Factory\ChoicesBuilderFactory;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[AsFilterElement(type: self::TYPE)]
class FieldValueChoiceFilterElement extends AbstractFilterElement
{
    public const TYPE = 'flare_fieldValueChoice';

    private array $foreignValueCache = [];
    private array $localValueCache = [];

    public function __construct(
        private readonly Connection $connection,
        private readonly ChoicesBuilderFactory $choicesBuilderFactory,
    ) {}

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('intrinsic')->default(false)->allowedTypes('bool');
        $resolver->define('field')->default(null)->allowedTypes('string', 'null');
        $resolver->define('multiple')->default(false)->allowedTypes('bool');
        $resolver->define('expanded')->default(false)->allowedTypes('bool');
        $resolver->define('preselect')->default(null)->allowedTypes('array', 'null');
    }

    protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void
    {
        $multiple = (bool) $model->isMultiple;

        $config
            ->set('intrinsic', (bool) $model->intrinsic)
            ->set('field', $model->fieldGeneric ?: null)
            ->set('multiple', $multiple)
            ->set('expanded', (bool) $model->isExpanded)
            ->set('preselect', $this->normalizePreselect($model->preselect, $multiple));
    }

    public function buildForm(FormBuilderInterface $builder, FilterContext $context): void
    {
        $config = $context->config;

        if ($config['intrinsic']) {
            return;
        }

        $choicesBuilder = $this->createChoices($context->list->dc, (string) ($config['field'] ?? ''))
            ->setEmptyOption(!$config['multiple']);

        $builder->add(FilterContext::FIELD_VALUE, ChoiceType::class, [
            'label' => false,
            'multiple' => $config['multiple'],
            'expanded' => $config['expanded'],
            'required' => false,
            'choice_loader' => $choicesBuilder->buildCallbackChoiceLoader(),
            'choice_label' => $choicesBuilder->buildChoiceLabelCallback(),
            'choice_value' => $choicesBuilder->buildChoiceValueCallback(),
            'data' => $this->buildPreselectData($choicesBuilder, $config),
        ]);

        $builder->setAttribute('flare.choices_builder', $choicesBuilder);
    }

    public function buildFilter(FilterBuilderInterface $builder, FilterContext $context, array $data): void
    {
        if ($context->engineContext instanceof ValidationContext) {
            return;
        }

        $config = $context->config;

        if (!$field = $config['field']) {
            return;
        }

        $value = $config['intrinsic']
            ? $config['preselect']
            : $this->normalizeRuntimeValue($data[FilterContext::FIELD_VALUE] ?? null, $context);

        if (!$value) {
            return;
        }

        $builder->add(FieldValueChoiceFilterType::class, [
            'field' => $field,
            'values' => $value,
        ]);
    }

    public function buildDca(DcaBuilder $dca, DcaContext $context): void
    {
        $dca->palette('{filter_legend},fieldGeneric,isMultiple,isExpanded,preselect');

        $dca->field('isMultiple')->eval(['submitOnChange' => true, 'tl_class' => 'cbx m12 w25']);
        $dca->field('isExpanded')->eval(['submitOnChange' => false, 'tl_class' => 'cbx m12 w25']);

        $table = $context->listModel->dc;
        $valueField = $context->filterModel?->fieldGeneric;

        if (!$table || !$valueField) {
            return;
        }

        $dca->field('preselect')
            ->inputType('select')
            ->eval([
                'multiple' => (bool) $context->filterModel?->isMultiple,
                'chosen' => true,
                'includeBlankOption' => true,
            ])
            ->options(function (?DataContainer $dc) use ($table, $valueField): array {
                Controller::loadDataContainer($table);

                return $this->createChoices($table, $valueField)
                    ->setModelSuffix('[%id%]')
                    ->buildOptions();
            });
    }

    /**
     * Builds the frontend/backend choices from the target field's values: foreign-key labels
     * when the field has a foreignKey relation, distinct local column values otherwise.
     */
    private function createChoices(string $table, string $field): ChoicesBuilder
    {
        $choices = $this->choicesBuilderFactory
            ->createChoicesBuilder()
            ->enable();

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

        return $choices;
    }

    /**
     * Computes the pre-fill data for the choice child from the preselect config, replicating
     * the former HydrateFormContract::hydrateForm() logic.
     *
     * @param array<string, mixed> $config
     */
    private function buildPreselectData(ChoicesBuilder $choicesBuilder, array $config): mixed
    {
        if (!$preselect = $config['preselect']) {
            return null;
        }

        $choices = $choicesBuilder->buildChoices();

        $data = [];
        foreach ($preselect as $alias) {
            if ($choice = $choices[$alias] ?? null) {
                $data[] = $choice;
            }
        }

        if (!$config['multiple']) {
            return \reset($data) ?: null;
        }

        return $data;
    }

    /**
     * Maps the submitted model data (choices) back to their scalar values — replicating the
     * former view-data extraction — and applies the old submitted-data normalization.
     */
    private function normalizeRuntimeValue(mixed $value, FilterContext $context): ?array
    {
        if (\is_null($value) || $value === '' || $value === []) {
            return null;
        }

        $choicesBuilder = $this->createChoices($context->list->dc, (string) ($context->config['field'] ?? ''));
        $choices = $choicesBuilder->buildChoices();
        $toValue = $choicesBuilder->buildChoiceValueCallback();

        $values = [];

        foreach ((array) $value as $choice)
        {
            if ($choice === ChoicesBuilder::EMPTY_CHOICE || \in_array($choice, $choices, true))
            {
                $values[] = (string) $toValue($choice);
                continue;
            }

            if (\is_scalar($choice) || $choice instanceof \Stringable) {
                $values[] = (string) $choice;
            }
        }

        return $this->extractSubmittedData($values);
    }

    private function normalizePreselect(mixed $preselect, bool $multiple): ?array
    {
        if (!$preselect) {
            return null;
        }

        if (\is_array($preselect)) {
            return $preselect;
        }

        if ($multiple
            || (\is_string($preselect) && \preg_match('/^a:\d+:\{.*}$/', $preselect)))
        {
            return StringUtil::deserialize($preselect, true);
        }

        return [$preselect];
    }

    /**
     * @param list<string> $submittedData
     */
    private function extractSubmittedData(array $submittedData): ?array
    {
        $submittedData = \array_filter($submittedData);
        $submittedData = \array_map('strtolower', \array_map('trim', $submittedData));
        $submittedData = \array_filter(
            $submittedData,
            static fn(string $value): bool => $value !== '' && $value !== ChoicesBuilder::EMPTY_CHOICE,
        );

        return $submittedData ?: null;
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
