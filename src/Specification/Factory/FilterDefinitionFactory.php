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
            intrinsic: $filterModel->intrinsic,
            alias: $filterModel->getFormName(),
            targetAlias: $filterModel->targetAlias,
            sourceFilterModel: $filterModel,
        );

        $self->setProperties($filterModel->row());

        return $self;
    }
}