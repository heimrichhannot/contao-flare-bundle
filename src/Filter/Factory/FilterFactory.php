<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Factory;

use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\DependencyInjection\Compiler\ValueClassReflector;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Exception\ValueClassMismatchError;
use HeimrichHannot\FlareBundle\Filter\Control\FilterControlInterface;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\ControlBinding;
use HeimrichHannot\FlareBundle\Filter\ElementBinding;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterOptionsResolver;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterTransformerResolver;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Registry\FilterControlRegistry;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Util\Str;

/**
 * Creates {@see Filter} DTOs, resolving registered element and control types to their services.
 */
final readonly class FilterFactory
{
    public function __construct(
        private FilterElementRegistry     $filterElementRegistry,
        private FilterControlRegistry     $filterControlRegistry,
        private FilterOptionsResolver     $filterOptionsResolver,
        private FilterTransformerResolver $filterTransformerResolver,
    ) {}

    /**
     * Creates a filter — the single construction path, also behind
     * {@see \HeimrichHannot\FlareBundle\Contao\Factory\FilterFactory}. With a control, the value is the filter's
     * initial value; without one, it is the fixed value.
     *
     * @param FilterElementInterface|string $element Filter element instance or registered type alias.
     * @param string|null $alias Required with a control: the alias names the filter's form field, which must stay the
     *   same across requests. Without a control, a missing alias is generated.
     * @param array<string, mixed> $config Element config, resolved through the element's schema.
     * @param FilterControlInterface|string|null $control Filter control instance or registered control type.
     * @param array<string, mixed> $controlConfig Control config, resolved through the control's schema.
     * @param object|null $configSource A stored source (e.g. a model) the element's and the control's transformers
     *   read their config from ({@see \HeimrichHannot\FlareBundle\Contract\TransformerContract}); `$config` and
     *   `$controlConfig` are merged on top, explicit keys win. Null runs no transformer.
     *
     * @throws FlareException If a control is given without an alias or with one that is no valid form name, the
     *   element or control cannot be resolved, a config does not satisfy its schema, or the control does not match
     *   the element's value class.
     * @throws ValueClassMismatchError If the value does not match the element's value class — a programming error.
     *
     * @see Filter::__construct for the remaining parameters.
     */
    public function create(
        FilterElementInterface|string      $element,
        ?string                            $alias = null,
        array                              $config = [],
        FilterControlInterface|string|null $control = null,
        array                              $controlConfig = [],
        ?ValueInterface                    $value = null,
        ?string                            $targetAlias = null,
        bool                               $targetingForced = false,
        ?object                            $configSource = null,
        ?string                            $source = null,
    ): Filter {
        $type = $this->resolveType($element, $source);

        if ($control !== null && ($alias === null || $alias === ''))
        {
            throw new FlareException(\sprintf(
                'A filter of type "%s" with a control needs an alias: it names the form field, which must stay the'
                . ' same across requests%s.',
                $type,
                $source ? " ({$source})" : '',
            ), method: __METHOD__);
        }

        if ($control !== null) {
            self::assertFormName($alias, $source);
        }

        $element = $this->resolveElement($element, $source);
        $valueClass = $this->getElementValueClass($type, $element);

        $alias = $alias !== null && $alias !== '' ? $alias : self::generateAlias($type);

        if ($configSource !== null) {
            $transformed = $this->filterTransformerResolver->transform($element, $type, $configSource, $source);
            $config = [...$transformed ?? [], ...$config];
        }

        $resolvedConfig = $this->filterOptionsResolver->resolve($element, $config, $source);

        $controlBinding = null;

        if ($control !== null)
        {
            $controlType = \is_string($control) ? $control : null;
            $control = $this->resolveControl($control, $valueClass, $source);

            if ($configSource !== null) {
                $transformed = $this->filterTransformerResolver
                    ->transform($control, $controlType ?? $control::class, $configSource, $source);
                $controlConfig = [...$transformed ?? [], ...$controlConfig];
            }

            $controlBinding = new ControlBinding(
                component: $control,
                type: $controlType ?? $control::class,
                config: $this->filterOptionsResolver->resolve($control, $controlConfig, $source),
            );
        }

        return new Filter(
            element: new ElementBinding($element, $type, $resolvedConfig, $valueClass),
            alias: $alias,
            control: $controlBinding,
            value: $value,
            targetAlias: $targetAlias,
            targetingForced: $targetingForced,
            source: $source,
        );
    }

    /**
     * The alias of a filter with a control names its form field, so it must be a valid form name.
     *
     * @throws FlareException
     */
    private static function assertFormName(string $alias, ?string $source): void
    {
        if (!Str::isValidFormName($alias)) {
            throw new FlareException(\sprintf(
                'The alias "%s" of a filter with a control is not a valid form name%s.',
                $alias,
                $source ? " ({$source})" : '',
            ), method: __METHOD__, source: $source);
        }
    }

    /**
     * A random alias for a filter without a control: `_.{type}_{random}`, which deliberately never mounts a form field
     * ({@see Str::isValidFormName()}).
     */
    private static function generateAlias(string $type): string
    {
        return \sprintf('_.%s_%s', $type, Str::random(8));
    }

    /**
     * The value class of an element: from its registration attribute, or — for elements without one,
     * e.g. inline instances — from the {@see AsFilterElement} attribute on its class.
     *
     * @return class-string<ValueInterface>|null
     */
    private function getElementValueClass(string $type, FilterElementInterface $element): ?string
    {
        if ($attribute = $this->filterElementRegistry->getAttribute($type)) {
            return $attribute->value;
        }

        $attributes = (new \ReflectionClass($element))->getAttributes(AsFilterElement::class);

        return isset($attributes[0]) ? $attributes[0]->newInstance()->value : null;
    }

    /**
     * @param class-string<ValueInterface>|null $valueClass The element's value class.
     *
     * @throws FlareException If the control is unknown or produces a value class other than the element's.
     */
    private function resolveControl(
        FilterControlInterface|string $control,
        ?string                       $valueClass,
        ?string                       $source,
    ): FilterControlInterface {
        $at = $source ? " ({$source})" : '';

        $controlValueClass = null;

        if (\is_string($control))
        {
            $controlType = $control;
            $control = $this->filterControlRegistry->get($controlType)
                ?? throw new FlareException(
                    \sprintf('Filter control "%s" not found%s.', $controlType, $at),
                    method: __METHOD__,
                    source: $source,
                );
            $controlValueClass = $this->filterControlRegistry->getValueClass($controlType);
        }

        // An inline instance is not registered: its value class comes from its narrowed decode() return type.
        try
        {
            $controlValueClass ??= ValueClassReflector::fromReturnType($control::class, 'decode');
        }
        catch (\InvalidArgumentException $e)
        {
            throw new FlareException($e->getMessage(), previous: $e, method: __METHOD__, source: $source);
        }

        if ($controlValueClass !== $valueClass)
        {
            throw new FlareException(\sprintf(
                'Filter control "%s" produces "%s", but the filter element expects "%s"%s.',
                $control::class,
                $controlValueClass,
                $valueClass ?? 'no value',
                $at,
            ), method: __METHOD__, source: $source);
        }

        return $control;
    }

    /**
     * @throws FlareException
     */
    private function resolveType(FilterElementInterface|string $element, ?string $source = null): string
    {
        if (!$type = \is_object($element) ? \get_class($element) : $element)
        {
            throw new FlareException(\sprintf(
                'A filter element instance or registered type alias must be provided%s.',
                $source ? " ({$source})" : ""
            ), method: __METHOD__);
        }

        return $type;
    }

    /**
     * @throws FlareException
     */
    private function resolveElement(
        FilterElementInterface|string $element,
        ?string                       $source = null
    ): FilterElementInterface {
        if ($element instanceof FilterElementInterface) {
            return $element;
        }

        return $this->filterElementRegistry->getService($element)
            ?? throw new FlareException(\sprintf(
                'Filter element type "%s" not found%s',
                $element,
                $source ? " ({$source})" : ""
            ), method: __METHOD__);
    }
}
