<?php

namespace HeimrichHannot\FlareBundle\Util;

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
            if (\method_exists($list, 'getInfer' . \ucfirst($property))) {
                $arguments[$property] = $list->{'getInfer' . \ucfirst($property)}();
                continue;
            }

            if (\method_exists($list, 'get' . \ucfirst($property))) {
                $arguments[$property] = $list->{'get' . \ucfirst($property)}();
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