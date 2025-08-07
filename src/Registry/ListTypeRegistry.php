<?php /** @noinspection PhpRedundantMethodOverrideInspection */

namespace HeimrichHannot\FlareBundle\Registry;

use HeimrichHannot\FlareBundle\DependencyInjection\Registry\AbstractServiceDescriptorRegistry;
use HeimrichHannot\FlareBundle\Registry\Descriptor\ListTypeDescriptor;

/**
 * {@inheritdoc}
 *
 * @template TDescriptor of ListTypeDescriptor
 */
class ListTypeRegistry extends AbstractServiceDescriptorRegistry
{
    public function getDescriptorClass(): string
    {
        return ListTypeDescriptor::class;
    }
}