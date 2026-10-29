<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\DataContainer\Inspection\Stub;

use HeimrichHannot\FlareBundle\Filter\Control\FilterControlInterface;
use HeimrichHannot\FlareBundle\Filter\ControlScope;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

final class CheckboxStubControl implements FilterControlInterface
{
    public function buildForm(FilterFormBuilderInterface $builder, ControlScope $scope): void
    {
        $builder->single(CheckboxType::class, ['required' => false]);
    }

    public function decode(mixed $data, ControlScope $scope): ?BoolValue
    {
        return $data === true ? new BoolValue(true) : null;
    }

    public function hydrate(?ValueInterface $value, ControlScope $scope): mixed
    {
        return null;
    }
}
