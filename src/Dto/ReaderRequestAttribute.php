<?php

namespace HeimrichHannot\FlareBundle\Dto;

use Contao\Model;
use HeimrichHannot\FlareBundle\Model\ListModel;

readonly class ReaderRequestAttribute
{
    public function __construct(
        private Model     $model,
        private ListModel $listModel,
    ) {}

    public function getModel(): Model
    {
        return $this->model;
    }

    public function getListModel(): ListModel
    {
        return $this->listModel;
    }

    public function marshall(): array
    {
        return [
            'model_class' => $this->model::class,
            'model_table' => $this->model::getTable(),
            'model_id' => $this->model->id,
            'list_id' => $this->listModel->id,
        ];
    }

    public static function unmarshall(array $data): ?self
    {
        $modelClass = $data['model_class'] ?? null;
        $modelTable = $data['model_table'] ?? null;
        $modelId = $data['model_id'] ?? null;
        $listId = $data['list_id'] ?? null;

        if (!$modelClass || !$modelTable || !$modelId || !$listId) {
            return null;
        }

        if (!\class_exists($modelClass) || !\is_a($modelClass, Model::class)) {
            return null;
        }

        /** @var Model $model */
        $model = $modelClass::findByPk($modelId);
        $listModel = ListModel::findByPk($listId);

        if (!$model || !$listModel) {
            throw new \InvalidArgumentException('Invalid data for ReaderRequestAttribute unmarshalling.');
        }

        return new self($model, $listModel);
    }
}