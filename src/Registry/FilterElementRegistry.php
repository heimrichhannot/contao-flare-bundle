<?php /** @noinspection PhpRedundantMethodOverrideInspection */

namespace HeimrichHannot\FlareBundle\Registry;

use HeimrichHannot\FlareBundle\DependencyInjection\Registry\AbstractServiceDescriptorRegistry;
use HeimrichHannot\FlareBundle\Registry\Descriptor\FilterElementDescriptor;

/**
 * {@inheritdoc}
 *
 * @template TDescriptor of FilterElementDescriptor
 */
class FilterElementRegistry extends AbstractServiceDescriptorRegistry
{
    public function getDescriptorClass(): string
    {
        return FilterElementDescriptor::class;
    }
}