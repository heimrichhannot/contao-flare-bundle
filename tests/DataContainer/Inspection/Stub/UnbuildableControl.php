<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\DataContainer\Inspection\Stub;

use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\Control\FilterControlInterface;
use HeimrichHannot\FlareBundle\Filter\ControlScope;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Form\FilterFormBuilderInterface;

final class UnbuildableControl implements FilterControlInterface
{
    public function buildForm(FilterFormBuilderInterface $builder, ControlScope $scope): void
    {
        throw new FilterException('cannot build');
    }

    public function decode(mixed $data, ControlScope $scope): ?BoolValue
    {
        return null;
    }

    public function hydrate(?ValueInterface $value, ControlScope $scope): mixed
    {
        return null;
    }
}
