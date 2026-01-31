<?php

namespace HeimrichHannot\FlareBundle\Specification\Factory;

use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Specification\FilterDefinition;

class FilterDefinitionFactory
{
    public function createFromFilterModel(FilterModel $filterModel): FilterDefinition
    {
        $self = new FilterDefinition(
            type: $filterModel->type,
            title: $filterModel->title,
            intrinsic: $filterModel->intrinsic,
            sourceFilterModel: $filterModel,
            filterFormFieldName: $filterModel->getFormName(),
            targetAlias: $filterModel->targetAlias,
        );

        $self->setProperties($filterModel->row());

        return $self;
    }
}