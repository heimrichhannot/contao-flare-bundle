<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Reader;

use Contao\Model;
use HeimrichHannot\FlareBundle\List\ListSpec;

readonly class ReaderRequestAttribute
{
    public function __construct(
        private Model    $model,
        private ListSpec $list,
    ) {}

    public function getModel(): Model
    {
        return $this->model;
    }

    public function getList(): ListSpec
    {
        return $this->list;
    }

    public function marshal(): array
    {
        return [
            'model_class' => $this->model::class,
            'model_table' => $this->model::getTable(),
            'model_id' => $this->model->id,
            'list_id' => $this->list->config['id'] ?? null,
        ];
    }
}