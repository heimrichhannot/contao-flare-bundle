<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Control;

use HeimrichHannot\FlareBundle\Config\TransformerResolver;
use HeimrichHannot\FlareBundle\Contract\DcaContract;
use HeimrichHannot\FlareBundle\Contract\OptionsContract;
use HeimrichHannot\FlareBundle\Contract\TransformerContract;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilderInterface;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\Filter\ExpectsValueTrait;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Base class for filter controls: no-op defaults for the optional config contracts.
 *
 * @api
 */
abstract class AbstractFilterControl implements FilterControlInterface, OptionsContract, TransformerContract, DcaContract
{
    use ExpectsValueTrait;

    public function configureOptions(OptionsResolver $resolver): void {}

    public function configureTransformers(TransformerResolver $resolver): void {}

    public function buildDca(DcaBuilderInterface $builder, DcaContext $context): void {}
}
