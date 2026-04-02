<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Reader\Factory;

use Contao\Model;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Reader\ReaderRequestAttribute;
use HeimrichHannot\FlareBundle\Specification\Factory\ListSpecificationFactory;

final readonly class ReaderRequestAttributeFactory
{
    public function __construct(
        private ListSpecificationFactory $listSpecificationFactory,
    ) {}

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

        /** @var Model $model */
        $model = $modelClass::findByPk($modelId);
        $listModel = ListModel::findByPk($listId);

        if (!$model || !$listModel) {
            throw new \InvalidArgumentException('Invalid data for ReaderRequestAttribute unmarshalling.');
        }

        $spec = $this->listSpecificationFactory->create($listModel);

        return new ReaderRequestAttribute($model, $spec);
    }
}