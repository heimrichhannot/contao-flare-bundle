<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Element;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\TransformerBuilder;
use HeimrichHannot\FlareBundle\Contract\DcaContract;
use HeimrichHannot\FlareBundle\Contract\IsSupportedContract;
use HeimrichHannot\FlareBundle\Contract\OptionsContract;
use HeimrichHannot\FlareBundle\Contract\TransformerContract;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

abstract class AbstractFilterElement implements
    FilterElementInterface, OptionsContract, TransformerContract, IsSupportedContract, DcaContract
{
    abstract public function configureOptions(OptionsResolver $resolver): void;

    public function configureTransformers(TransformerBuilder $transformers): void
    {
        $transformers->for(FilterModel::class, $this->transformFilterModel(...));
    }

    /**
     * Translates a stored tl_flare_filter model into canonical config values (unresolved).
     * All deserialization, casting, and enum parsing belongs here.
     */
    abstract protected function transformFilterModel(FilterModel $model, ConfigBuilder $config): void;

    public function buildDca(DcaBuilder $dca, DcaContext $context): void {}

    public function buildForm(FormBuilderInterface $builder, FilterContext $context): void {}

    public function buildFilter(FilterBuilderInterface $builder, FilterContext $context, array $data): void {}

    public function isSupported(): bool
    {
        return true;
    }
}
