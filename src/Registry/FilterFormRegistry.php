<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Registry;

use HeimrichHannot\FlareBundle\DependencyInjection\Compiler\RegisterFilterFormsPass;
use HeimrichHannot\FlareBundle\Filter\Form\FilterFormInterface;
use Psr\Container\ContainerInterface;

/**
 * Maps filter form names to their registration metadata and, lazily, to their form services.
 *
 * Populated at compile time by {@see RegisterFilterFormsPass}. Unlike {@see FilterElementRegistry},
 * the services arrive as a `container.service_locator` rather than as method-call arguments: every
 * metadata read — the `formVariant` options callback, the election below, DCA composition — is
 * served from plain arrays and instantiates nothing. Only {@see getService()} touches a service,
 * because a list uses a handful of forms rather than all of them.
 *
 * The name is the value persisted in `tl_flare_filter.formVariant`. The empty string is reserved
 * for "no form" (intrinsic) and is never a registry key.
 *
 * @phpstan-type FilterFormMeta array{
 *     value: class-string|null,
 *     requires: list<class-string>,
 *     default: bool,
 *     service: string
 * }
 */
final class FilterFormRegistry
{
    /**
     * @param ContainerInterface|null $formLocator Locator keyed by form name.
     * @param array<string, FilterFormMeta> $forms Keyed by form name, in registration order.
     */
    public function __construct(
        private readonly ?ContainerInterface $formLocator = null,
        private readonly array               $forms = [],
    ) {}

    public function has(string $name): bool
    {
        return isset($this->forms[$name]);
    }

    /**
     * Registered form names, in compile-time registration order.
     *
     * @return list<string>
     * @api
     */
    public function keys(): array
    {
        return \array_keys($this->forms);
    }

    /**
     * Resolves the form service. Returns null for an unknown name — a `formVariant` value whose
     * form was renamed or uninstalled is a data condition, not a programming error, so the caller
     * decides whether that degrades to intrinsic or aborts.
     *
     * @throws \LogicException If metadata was registered without a locator (mis-wired container).
     * @api
     */
    public function getService(?string $name): ?FilterFormInterface
    {
        if ($name === null || !isset($this->forms[$name])) {
            return null;
        }

        if ($this->formLocator === null) {
            throw new \LogicException(\sprintf(
                'Filter form "%s" is registered but no form locator was injected into %s. Did %s run?',
                $name,
                self::class,
                RegisterFilterFormsPass::class,
            ));
        }

        if (!$this->formLocator->has($name)) {
            return null;
        }

        $service = $this->formLocator->get($name);

        return $service instanceof FilterFormInterface ? $service : null;
    }

    /**
     * The value class the form produces, or null if the form declares none.
     *
     * @return class-string|null
     * @api
     */
    public function getValueClass(?string $name): ?string
    {
        return $name !== null ? ($this->forms[$name]['value'] ?? null) : null;
    }

    /**
     * @return list<class-string>
     * @api
     */
    public function getRequires(?string $name): array
    {
        return $name !== null ? ($this->forms[$name]['requires'] ?? []) : [];
    }

    /** @api */
    public function isDefault(?string $name): bool
    {
        return $name !== null && ($this->forms[$name]['default'] ?? false);
    }

    /**
     * The service id behind a form name. For diagnostics and error messages only.
     *
     * @api
     */
    public function getServiceId(?string $name): ?string
    {
        return $name !== null ? ($this->forms[$name]['service'] ?? null) : null;
    }

    /**
     * Elects the forms usable for an element: the value class must match and every entry in the
     * form's `requires` must be implemented by the element (SPEC_FILTER_FORMS.md §3.4).
     *
     * The element is required rather than optional: an election without one cannot honour
     * `requires`, and returning every form for the value class would feed a backend select
     * directly.
     *
     * @param class-string|null $valueClass The element's declared value class.
     * @param object|class-string $element The element service or its class.
     * @return list<string> Form names, in registration order.
     * @api
     */
    public function findNames(?string $valueClass, object|string $element): array
    {
        if ($valueClass === null) {
            return [];
        }

        $names = [];

        foreach ($this->forms as $name => $meta)
        {
            if ($meta['value'] !== $valueClass) {
                continue;
            }

            if (!$this->satisfies($element, $meta['requires'])) {
                continue;
            }

            $names[] = $name;
        }

        return $names;
    }

    /**
     * The fallback form for a value class: the eligible form flagged `default`, else the first
     * eligible one, else null. A default form the element cannot satisfy is skipped.
     *
     * @param class-string|null $valueClass
     * @param object|class-string $element
     * @api
     */
    public function findDefaultName(?string $valueClass, object|string $element): ?string
    {
        $names = $this->findNames($valueClass, $element);

        foreach ($names as $name)
        {
            if ($this->forms[$name]['default']) {
                return $name;
            }
        }

        return $names[0] ?? null;
    }

    /**
     * @param object|class-string $element
     * @param list<class-string> $requires
     */
    private function satisfies(object|string $element, array $requires): bool
    {
        foreach ($requires as $interface)
        {
            if (!\is_a($element, $interface, true)) {
                return false;
            }
        }

        return true;
    }
}
