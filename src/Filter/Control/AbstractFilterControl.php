<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Control;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\TransformerMap;
use HeimrichHannot\FlareBundle\Contract\DcaContract;
use HeimrichHannot\FlareBundle\Contract\OptionsContract;
use HeimrichHannot\FlareBundle\Contract\TransformerContract;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilderInterface;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\Filter\CallbackFilterModelTransformer;
use HeimrichHannot\FlareBundle\Filter\ControlScope;
use HeimrichHannot\FlareBundle\Filter\ExpectsValueTrait;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Base class for filter controls, the counterpart of
 * {@see \HeimrichHannot\FlareBundle\Filter\Element\AbstractFilterElement}: the control config is read from
 * the stored tl_flare_filter model by {@see self::transformFilterModel()} and resolved by
 * {@see self::configureOptions()}.
 *
 * @api
 */
abstract class AbstractFilterControl implements FilterControlInterface, OptionsContract, TransformerContract, DcaContract
{
    use ExpectsValueTrait;

    abstract public function configureOptions(OptionsResolver $resolver): void;

    public function configureTransformers(TransformerMap $transformers): void
    {
        $transformers->set(
            sourceClass: FilterModel::class,
            transformer: new CallbackFilterModelTransformer($this->transformFilterModel(...)),
        );
    }

    /**
     * Translates a stored tl_flare_filter model into canonical control config values (unresolved).
     * All deserialization, casting, and enum parsing belongs here.
     */
    abstract protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void;

    public function buildDca(DcaBuilderInterface $builder, DcaContext $context): void {}

    abstract public function buildForm(FilterFormBuilderInterface $builder, ControlScope $scope): void;

    abstract public function decode(mixed $data, ControlScope $scope): ?ValueInterface;

    abstract public function hydrate(?ValueInterface $value, ControlScope $scope): mixed;
}
