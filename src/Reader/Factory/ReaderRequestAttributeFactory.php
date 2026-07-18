<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Reader\Factory;

use Contao\Model;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Reader\ReaderRequestAttribute;

final readonly class ReaderRequestAttributeFactory
{
    public function createFromModels(Model $displayModel, ListModel $listModel): ReaderRequestAttribute
    {
        return new ReaderRequestAttribute($displayModel, $listModel);
    }

    public function createFromData(array $data): ?ReaderRequestAttribute
    {
        $modelClass = $data['model_class'] ?? null;
        $modelTable = $data['model_table'] ?? null;
        $modelId = $data['model_id'] ?? null;
        $listId = $data['list_id'] ?? null;

        if (!$modelClass || !$modelTable || !$modelId || !$listId) {
            return null;
        }

        if (!\class_exists($modelClass) || !\is_subclass_of($modelClass, Model::class)) {
            return null;
        }

        /** @var Model $displayModel */
        $displayModel = $modelClass::findByPk($modelId);
        $listModel = ListModel::findByPk($listId);

        if (!$displayModel || !$listModel) {
            throw new \InvalidArgumentException('Invalid data for ReaderRequestAttribute unmarshalling.');
        }

        return new ReaderRequestAttribute($displayModel, $listModel);
    }
}
