<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Element;

use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\TransformerMap;
use HeimrichHannot\FlareBundle\Contract\DcaContract;
use HeimrichHannot\FlareBundle\Contract\IsSupportedContract;
use HeimrichHannot\FlareBundle\Contract\OptionsContract;
use HeimrichHannot\FlareBundle\Contract\TransformerContract;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilderInterface;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\Filter\CallbackFilterModelTransformer;
use HeimrichHannot\FlareBundle\Filter\ExpectsValueTrait;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilder;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractFilterElement implements
    FilterElementInterface, DcaContract, IsSupportedContract, OptionsContract, TransformerContract
{
    use ExpectsValueTrait;

    private Connection $connection;

    abstract public function configureOptions(OptionsResolver $resolver): void;

    public function configureTransformers(TransformerMap $transformers): void
    {
        $transformers->set(
            sourceClass: FilterModel::class,
            transformer: new CallbackFilterModelTransformer($this->transformFilterModel(...)),
        );
    }

    /**
     * Translates a stored tl_flare_filter model into canonical config values (unresolved).
     * All deserialization, casting, and enum parsing belongs here.
     */
    abstract protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void;

    public function buildDca(DcaBuilderInterface $builder, DcaContext $context): void {}

    public function buildContext(FilterContextBuilder $builder, ?ValueInterface $value): void {}

    public function isSupported(): bool
    {
        return true;
    }

    #[Required]
    public function setConnection(Connection $connection): void
    {
        $this->connection = $connection;
    }

    protected function getConnection(): Connection
    {
        return $this->connection;
    }
}
