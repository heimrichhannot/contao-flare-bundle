<?php

namespace HeimrichHannot\FlareBundle\Registry;

use HeimrichHannot\FlareBundle\DependencyInjection\Registry\AbstractPriorityServiceDescriptorRegistry;
use HeimrichHannot\FlareBundle\Registry\Descriptor\FlareCallbackDescriptor;

/**
 * {@inheritdoc}
 *
 * @internal For internal use only. API might change without notice.
 *
 * @template TDescriptor of FlareCallbackDescriptor
 */
class FlareCallbackRegistry extends AbstractPriorityServiceDescriptorRegistry
{
    public function getDescriptorClass(): string
    {
        return FlareCallbackDescriptor::class;
    }
}