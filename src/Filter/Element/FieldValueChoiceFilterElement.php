<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Element;

use Contao\Controller;
use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Contract\FilterElement\ChoiceSourceContract;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilderInterface;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\ElementScope;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Predicate\FieldValueChoicePredicate;
use HeimrichHannot\FlareBundle\Filter\Value\Choice\ValueChoice;
use HeimrichHannot\FlareBundle\Filter\Value\ChoiceValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilder;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[AsFilterElement(type: self::TYPE, value: ChoiceValue::class)]
class FieldValueChoiceFilterElement extends AbstractFilterElement implements ChoiceSourceContract
{
    public const TYPE = 'flare_fieldValueChoice';

    private array $foreignValueCache = [];
    private array $localValueCache = [];

    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('field')->default(null)->allowedTypes('string', 'null');
    }

    protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void
    {
        $config->set('field', $model->elm_fieldGeneric ?: null);
    }

    /**
     * @throws FilterException
     */
    public function buildContext(
        FilterContextBuilderInterface $builder,
        ElementScope                  $scope,
        ?ValueInterface               $value,
    ): void {
        if (!$value = $this->expectValue($value, ChoiceValue::class)) {
            return;
        }

        // A value that cannot be applied must not widen the list.
        if (!$field = $scope->get('field')) {
            $builder->abort();
        }

        // The predicate compares LOWER(TRIM(field)), so the values are normalized alike;
        // a blank value would match every empty row.
        $values = [];

        foreach ($value->choices as $choice)
        {
            if ($choice instanceof ValueChoice && ($normalized = \mb_strtolower(\trim($choice->value))) !== '') {
                $values[] = $normalized;
            }
        }

        if (!$values) {
            $builder->abort();
        }

        $builder->addPredicate(FieldValueChoicePredicate::class, [
            'field' => $field,
            'values' => \array_values(\array_unique($values)),
        ]);
    }

    public function buildDca(DcaBuilderInterface $builder, DcaContext $context): void
    {
        $builder->palette('{filter_legend},elm_fieldGeneric');
    }

    /**
     * Builds the choices from the target field's values: foreign-key labels when the field has a
     * foreignKey relation, distinct local column values otherwise.
     *
     * @throws FilterException
     */
    public function buildChoices(ChoicesBuilder $choices, ElementScope $scope): void
    {
        $table = $scope->list->dc;

        if (!$field = (string) $scope->get('field')) {
            throw new FilterException('No target field configured.', method: __METHOD__);
        }

        if (!\is_null($foreignValues = $this->getForeignValues($table, $field)))
        {
            // TODO: display of frontend form values should be configurable
            foreach ($foreignValues as $id => $label) {
                $choices->add(new ValueChoice((string) $id, (string) $label));
            }
        }
        /** @mago-expect lint:no-else-clause This else clause is fine. */
        else
        {
            foreach ($this->getLocalValues($table, $field) as $value) {
                $choices->add(new ValueChoice((string) $value, (string) $value));
            }
        }
    }

    public function choiceFromKey(string $key): ValueChoice
    {
        return new ValueChoice($key);
    }

    private function getForeignValues(string $table, string $field): ?array
    {
        if (isset($this->foreignValueCache[$table][$field])) {
            return $this->foreignValueCache[$table][$field];
        }

        Controller::loadDataContainer($table);

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
