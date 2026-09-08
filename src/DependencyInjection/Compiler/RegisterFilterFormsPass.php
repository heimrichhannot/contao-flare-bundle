<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\DependencyInjection\Compiler;

use HeimrichHannot\FlareBundle\DataContainer\FilterContainer;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterForm;
use HeimrichHannot\FlareBundle\DependencyInjection\Factory\TypeNameFactory;
use HeimrichHannot\FlareBundle\Filter\Form\FilterFormInterface;
use HeimrichHannot\FlareBundle\Registry\FilterFormRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Collects `flare.filter_form` tags into {@see FilterFormRegistry}: metadata as plain arrays,
 * services behind a lazy `container.service_locator` keyed by form name.
 *
 * Also implements the SPEC_FILTER_FORMS.md §10 compile-time checks. Must run BEFORE
 * {@see RegisterFilterElementsPass}, which clears the `flare.filter_element` tags this pass reads.
 *
 * @phpstan-type FilterFormMeta array{
 *     value: class-string|null,
 *     requires: list<class-string>,
 *     default: bool,
 *     service: string
 * }
 */
final class RegisterFilterFormsPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(FilterFormRegistry::class)) {
            return;
        }

        $tag = AsFilterForm::TAG;

        /** @var array<string, FilterFormMeta> $forms */
        $forms = [];
        /** @var array<string, Reference> $locations */
        $locations = [];

        foreach ($this->findAndSortTaggedServices($tag, $container) as $reference)
        {
            $serviceId = (string) $reference;
            $definition = $container->findDefinition($serviceId);
            $tags = $definition->getTag($tag);
            $definition->clearTag($tag);

            $this->assertIsFilterForm($definition, $serviceId);

            foreach ($tags as $attributes)
            {
                $name = $this->getFilterFormName($definition, $attributes);

                if (isset($forms[$name]))
                {
                    throw new \InvalidArgumentException(\sprintf(
                        'The filter form name "%s" is already registered by service "%s"; service "%s" must'
                        . ' declare a different name. Form names are persisted in "%s.formVariant" and must be unique.',
                        $name,
                        $forms[$name]['service'],
                        $serviceId,
                        FilterContainer::TABLE_NAME,
                    ));
                }

                $forms[$name] = [
                    'value' => ((string) ($attributes['value'] ?? '')) ?: null,
                    'requires' => $this->getRequires($serviceId, $name, $attributes),
                    'default' => (bool) ($attributes['default'] ?? false),
                    'service' => $serviceId,
                ];

                $locations[$name] = new Reference($serviceId);

                $container
                    ->setAlias('flare.filter_form.' . $name, $serviceId)
                    ->setPublic(true);
            }
        }

        $this->assertOneDefaultPerValueClass($forms);
        $this->assertEveryElementValueIsServed($container, $forms);

        $registry = $container->findDefinition(FilterFormRegistry::class);
        $registry->setArgument('$forms', $forms);
        $registry->setArgument(
            '$formLocator',
            (new Definition(ServiceLocator::class, [$locations]))->addTag('container.service_locator'),
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    protected function getFilterFormName(Definition $definition, array $attributes): string
    {
        if ($name = (string) ($attributes['name'] ?? ''))
        {
            if ($name === 'default') {
                throw new \InvalidArgumentException(
                    'The filter form name "default" is reserved and cannot be used. Choose a different name.',
                );
            }

            return $name;
        }

        if (!$class = $definition->getClass()) {
            throw new \InvalidArgumentException(
                'A filter form service without a class must declare an explicit name.',
            );
        }

        $name = TypeNameFactory::createFilterFormType($class);

        // "FooFilterForm" reduces to "foo", but a class named exactly "FilterForm" reduces to "",
        // and the empty string is the intrinsic sentinel in tl_flare_filter.formVariant.
        if ($name === '' || $name === 'default')
        {
            throw new \InvalidArgumentException(\sprintf(
                'Cannot derive a filter form name from class "%s" (derived "%s"). Declare an explicit name.',
                $class,
                $name,
            ));
        }

        return $name;
    }

    private function assertIsFilterForm(Definition $definition, string $serviceId): void
    {
        $class = $definition->getClass();

        if ($class === null || !\class_exists($class)) {
            return;
        }

        if (!\is_a($class, FilterFormInterface::class, true))
        {
            throw new \InvalidArgumentException(\sprintf(
                'Service "%s" is tagged "%s" but "%s" does not implement %s.',
                $serviceId,
                AsFilterForm::TAG,
                $class,
                FilterFormInterface::class,
            ));
        }
    }

    /**
     * §10, row 3 (first half): every `requires` entry must be an existing interface.
     *
     * @param array<string, mixed> $attributes
     * @return list<class-string>
     */
    private function getRequires(string $serviceId, string $name, array $attributes): array
    {
        $requires = $attributes['requires'] ?? [];

        if (!\is_array($requires))
        {
            throw new \InvalidArgumentException(\sprintf(
                'The "requires" attribute of filter form "%s" (service "%s") must be a list of interface names.',
                $name,
                $serviceId,
            ));
        }

        $resolved = [];

        foreach ($requires as $interface)
        {
            if (!\is_string($interface) || !\interface_exists($interface))
            {
                throw new \InvalidArgumentException(\sprintf(
                    'Filter form "%s" (service "%s") requires "%s", which is not an existing interface.',
                    $name,
                    $serviceId,
                    \is_string($interface) ? $interface : \get_debug_type($interface),
                ));
            }

            $resolved[] = $interface;
        }

        return $resolved;
    }

    /**
     * §3.3: `default` names *the* fallback form for a value class.
     *
     * @param array<string, FilterFormMeta> $forms
     */
    private function assertOneDefaultPerValueClass(array $forms): void
    {
        $defaults = [];

        foreach ($forms as $name => $meta)
        {
            if (!$meta['default'] || $meta['value'] === null) {
                continue;
            }

            if (isset($defaults[$meta['value']]))
            {
                throw new \InvalidArgumentException(\sprintf(
                    'Filter forms "%s" and "%s" are both declared as the default for value class "%s".'
                    . ' Exactly one default per value class is allowed.',
                    $defaults[$meta['value']],
                    $name,
                    $meta['value'],
                ));
            }

            $defaults[$meta['value']] = $name;
        }
    }

    /**
     * §10, row 3 (second half): every element value class must be served by at least one form whose
     * `requires` that element satisfies. Reads `flare.filter_element` tags, hence the pass ordering.
     *
     * @param array<string, FilterFormMeta> $forms
     */
    private function assertEveryElementValueIsServed(ContainerBuilder $container, array $forms): void
    {
        foreach ($container->findTaggedServiceIds(AsFilterElement::TAG) as $serviceId => $tags)
        {
            $elementClass = $container->findDefinition($serviceId)->getClass();

            if ($elementClass === null || !\class_exists($elementClass)) {
                continue;
            }

            foreach ($tags as $attributes)
            {
                $value = ((string) ($attributes['value'] ?? '')) ?: null;

                if ($value === null || $this->hasEligibleForm($elementClass, $value, $forms)) {
                    continue;
                }

                throw new \InvalidArgumentException(\sprintf(
                    'Filter element "%s" declares value class "%s", but no filter form produces that value for an'
                    . ' element of type "%s". Register a form with #[AsFilterForm(value: %s::class)] whose'
                    . ' "requires" the element satisfies, or drop the "value" declaration to make the element'
                    . ' intrinsic-only.',
                    (string) ($attributes['type'] ?? $serviceId),
                    $value,
                    $elementClass,
                    $value,
                ));
            }
        }
    }

    /**
     * @param class-string $elementClass
     * @param array<string, FilterFormMeta> $forms
     */
    private function hasEligibleForm(string $elementClass, string $valueClass, array $forms): bool
    {
        foreach ($forms as $meta)
        {
            if ($meta['value'] !== $valueClass) {
                continue;
            }

            $satisfied = true;

            foreach ($meta['requires'] as $interface)
            {
                if (!\is_a($elementClass, $interface, true))
                {
                    $satisfied = false;

                    break;
                }
            }

            if ($satisfied) {
                return true;
            }
        }

        return false;
    }
}
