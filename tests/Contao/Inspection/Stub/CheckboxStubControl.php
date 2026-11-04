<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Contao\Inspection\Stub;

use HeimrichHannot\FlareBundle\Filter\Control\FilterControlInterface;
use HeimrichHannot\FlareBundle\Filter\ControlContext;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Form\FilterFormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

final class CheckboxStubControl implements FilterControlInterface
{
    public function buildForm(FilterFormBuilderInterface $builder, ControlContext $context): void
    {
        $builder->single(CheckboxType::class, ['required' => false]);
    }

    public function decode(mixed $data, ControlContext $context): ?BoolValue
    {
        return $data === true ? new BoolValue(true) : null;
    }

    public function hydrate(?ValueInterface $value, ControlContext $context): mixed
    {
        return null;
    }
}
