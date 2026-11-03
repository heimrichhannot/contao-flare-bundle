<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Value\Choice;

final class ChoiceKey
{
    /**
     * The canonical key: `"<scope>.<local>"`, or `"<local>"` without a scope.
     */
    public static function of(ChoiceObjectInterface $choice): string
    {
        $scope = $choice->getChoiceScope();

        return $scope === null
            ? $choice->getChoiceLocalKey()
            : $scope . '.' . $choice->getChoiceLocalKey();
    }
}
