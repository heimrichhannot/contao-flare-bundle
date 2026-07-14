<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Element;

use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\TransformerResolver;
use HeimrichHannot\FlareBundle\Contract\DcaContract;
use HeimrichHannot\FlareBundle\Contract\FilterElement\IntrinsicContract;
use HeimrichHannot\FlareBundle\Contract\IsSupportedContract;
use HeimrichHannot\FlareBundle\Contract\OptionsContract;
use HeimrichHannot\FlareBundle\Contract\TransformerContract;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\Filter\CallbackFilterModelTransformer;
use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilder;
use HeimrichHannot\FlareBundle\Form\Factory\ChoicesBuilderFactory;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractFilterElement implements
    FilterElementInterface, IntrinsicContract, DcaContract, IsSupportedContract, OptionsContract, TransformerContract
{
    private ChoicesBuilderFactory $choicesBuilderFactory;
    private Connection $connection;

    abstract public function configureOptions(OptionsResolver $resolver): void;

    public function configureTransformers(TransformerResolver $resolver): void
    {
        $resolver->for(
            sourceClass: FilterModel::class,
            transformer: new CallbackFilterModelTransformer($this->transformFilterModel(...)),
        );
    }

    /**
     * Translates a stored tl_flare_filter model into canonical config values (unresolved).
     * All deserialization, casting, and enum parsing belongs here.
     */
    abstract protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void;

    public function buildDca(DcaBuilder $dca, DcaContext $context): void {}

    public function buildForm(FormBuilderInterface $builder, FilterContext $context): void {}

    public function buildFilter(FilterBuilderInterface $builder, FilterContext $context, array $values): void {}

    public function isSupported(): bool
    {
        return true;
    }

    public function isOnlyIntrinsic(): bool
    {
        return false;
    }

    #[Required]
    public function setChoicesBuilderFactory(ChoicesBuilderFactory $choicesBuilderFactory): void
    {
        $this->choicesBuilderFactory = $choicesBuilderFactory;
    }

    protected function createChoicesBuilder(): ChoicesBuilder
    {
        return $this->choicesBuilderFactory->createChoicesBuilder();
    }

    #[Required]
    public function setConnection(Connection $connection): void
    {
        $this->connection = $connection;
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }
}
