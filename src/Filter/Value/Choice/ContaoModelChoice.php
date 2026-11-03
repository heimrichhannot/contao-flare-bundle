<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Value\Choice;

use Contao\Model;

/**
 * A choice identified by a Contao record: scope is the table, local key the id.
 */
final readonly class ContaoModelChoice implements ChoiceObjectInterface
{
    private function __construct(
        public string  $table,
        public int     $id,
        private ?Model $model = null,
    ) {}

    /**
     * For option lists: labels come from the row, the model is held internally and never hashed.
     */
    public static function from(Model $model): self
    {
        return new self($model::getTable(), (int) $model->id, $model);
    }

    /**
     * For programmatic values, without a database round trip.
     */
    public static function ref(string $table, int $id): self
    {
        return new self($table, $id);
    }

    public function getModel(): ?Model
    {
        return $this->model;
    }

    public function getChoiceScope(): string
    {
        return $this->table;
    }

    public function getChoiceLocalKey(): string
    {
        return (string) $this->id;
    }

    public function getLabelParameters(): array
    {
        if ($this->model === null) {
            return [];
        }

        $params = [];

        foreach ($this->model->row() as $field => $value)
        {
            if (\is_scalar($value) || $value === null) {
                $params['%' . $field . '%'] = $value;
            }
        }

        return $params;
    }
}
