<?php

namespace HeimrichHannot\FlareBundle\Manager;

use Contao\Model;
use HeimrichHannot\FlareBundle\Dto\ReaderRequestAttribute;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Specification\Factory\ListSpecificationFactory;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class RequestManager
{
    public function __construct(
        private RequestStack             $requestStack,
        private ListSpecificationFactory $listSpecificationFactory,
    ) {}

    public function setReader(ReaderRequestAttribute $attribute): void
    {
        $this->requestStack->getMainRequest()?->attributes->set('flare_reader', $attribute->marshall());
    }

    public function getReader(): ?ReaderRequestAttribute
    {
        $data = $this->requestStack->getMainRequest()?->attributes->get('flare_reader') ?? [];
        return $this->createReaderRequestAttributeFromData($data);
    }

    public function createReaderRequestAttributeFromData(array $data): ?ReaderRequestAttribute
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

        $spec = $this->listSpecificationFactory->create($listModel);

        return new ReaderRequestAttribute($model, $spec);
    }
}