<?php /** @noinspection PhpRedundantMethodOverrideInspection */

declare(strict_types=1);

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

    public function get(?string $alias): ?ListTypeDescriptor
    {
        $descriptor = parent::get($alias);

        if (!$descriptor instanceof ListTypeDescriptor) {
            return null;
        }

        return $descriptor;
    }
}