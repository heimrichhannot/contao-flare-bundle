<?php

namespace HeimrichHannot\FlareBundle\List;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\TransformerInterface;
use HeimrichHannot\FlareBundle\Model\ListModel;

final readonly class CallbackListModelTransformer implements TransformerInterface
{
    /** @param \Closure(ConfigBuilder $config, object $source): void $transform */
    public function __construct(private \Closure $transform) {}

    public function __invoke(ConfigBuilder $config, object $source): void
    {
        if (!$source instanceof ListModel) {
            throw new \InvalidArgumentException(\sprintf(
                'Invalid source object provided to ListModel transformer. Expected instance of %s, got %s.',
                ListModel::class,
                \get_class($source),
            ));
        }

        ($this->transform)($config, $source);
    }
}
