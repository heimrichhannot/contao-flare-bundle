<?php

namespace HeimrichHannot\FlareBundle\Dto;

use Contao\Model;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

readonly class ReaderRequestAttribute
{
    public function __construct(
        private Model             $model,
        private ListSpecification $listSpecification,
    ) {}

    public function getModel(): Model
    {
        return $this->model;
    }

    public function getListSpecification(): ListSpecification
    {
        return $this->listSpecification;
    }

    public function marshall(): array
    {
        $dataSource = $this->listSpecification->getDataSource();

        return [
            'model_class' => $this->model::class,
            'model_table' => $this->model::getTable(),
            'model_id' => $this->model->id,
            'list_id' => $dataSource instanceof ListModel ? $dataSource->id : null,
        ];
    }
}