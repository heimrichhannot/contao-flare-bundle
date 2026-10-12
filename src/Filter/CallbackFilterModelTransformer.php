<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\TransformerInterface;
use HeimrichHannot\FlareBundle\Model\FilterModel;

final readonly class CallbackFilterModelTransformer implements TransformerInterface
{
    /** @param \Closure(ConfigBuilder $config, object $source): void $transform */
    public function __construct(private \Closure $transform) {}

    public function __invoke(ConfigBuilder $config, object $source): void
    {
        if (!$source instanceof FilterModel) {
            throw new \InvalidArgumentException(\sprintf(
                'Invalid source object provided to FilterModel transformer. Expected instance of %s, got %s.',
                FilterModel::class,
                \get_class($source),
            ));
        }

        ($this->transform)($config, $source);
    }
}
