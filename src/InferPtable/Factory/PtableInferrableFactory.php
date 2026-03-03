<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\InferPtable\Factory;

use HeimrichHannot\FlareBundle\InferPtable\PtableInferrable;
use HeimrichHannot\FlareBundle\InferPtable\PtableInferrableInterface;

class PtableInferrableFactory
{
    public static function createFromListModelLike(object $list): ?PtableInferrable
    {
        if ($list instanceof PtableInferrableInterface) {
            return new PtableInferrable(
                fieldPid: $list->getInferFieldPid(),
                whichPtable: $list->getInferWhichPtable(),
                fieldPtable: $list->getInferFieldPtable(),
                tablePtable: $list->getInferTablePtable(),
            );
        }

        $properties = ['fieldPid', 'whichPtable', 'fieldPtable', 'tablePtable'];
        $arguments = [];

        foreach ($properties as $property)
        {
            $ucFirstProperty = \ucfirst($property);

            if (\method_exists($list, $method = 'getInfer' . $ucFirstProperty)) {
                $arguments[$property] = $list->{$method}();
                continue;
            }

            if (\method_exists($list, $method = 'get' . $ucFirstProperty)) {
                $arguments[$property] = $list->{$method}();
                continue;
            }

            if (\property_exists($list, $property) || \method_exists($list, '__get')) {
                $arguments[$property] = $list->{$property};
                continue;
            }

            return null;
        }

        return new PtableInferrable(...$arguments);
    }
}