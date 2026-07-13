<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Element;

use HeimrichHannot\FlareBundle\Contract\DcaContract;
use HeimrichHannot\FlareBundle\Contract\IsSupportedContract;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

abstract class AbstractFilterFilterElement implements
    FilterElementInterface, FilterElementOptionsInterface, IsSupportedContract, DcaContract
{
    abstract public function configureOptions(OptionsResolver $resolver): void;

    abstract public function configFromRow(array $row): array;

    public function buildDca(DcaBuilder $dca, DcaContext $context): void {}

    public function buildForm(FormBuilderInterface $builder, FilterContext $context): void {}

    public function buildFilter(FilterBuilderInterface $builder, FilterContext $context, array $data): void {}

    public function isSupported(): bool
    {
        return true;
    }
}
