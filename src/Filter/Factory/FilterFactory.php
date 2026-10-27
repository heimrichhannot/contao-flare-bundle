<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Factory;

use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\DependencyInjection\Compiler\ValueClassReflector;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Exception\UnreadableValueException;
use HeimrichHannot\FlareBundle\Filter\Control\FilterControlInterface;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\ControlBinding;
use HeimrichHannot\FlareBundle\Filter\ElementBinding;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterOptionsResolver;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterTransformerResolver;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Registry\FilterControlRegistry;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Registry\ValueProviderRegistry;
use HeimrichHannot\FlareBundle\Util\Str;
use Psr\Log\LoggerInterface;

/**
 * Creates {@see Filter} DTOs, resolving registered element and control types to their services.
 */
final readonly class FilterFactory
{
    public function __construct(
        private FilterElementRegistry     $filterElementRegistry,
        private FilterControlRegistry     $filterControlRegistry,
        private ValueProviderRegistry     $valueProviderRegistry,
        private FilterOptionsResolver     $filterOptionsResolver,
        private FilterTransformerResolver $filterTransformerResolver,
        private LoggerInterface           $logger,
    ) {}

    /**
     * Creates a filter programmatically. With a control, the value is the filter's initial value;
     * without one, it is the fixed value (SPEC_FILTER_FORMS.md §4.2).
     *
     * @param FilterElementInterface|string $element Filter element instance or registered type alias.
     * @param string|null $alias Required with a control: the alias names the filter's form field, which must stay the
     *   same across requests. Without a control, a missing alias is generated.
     * @param array<string, mixed> $config Element config, resolved through the element's schema.
     * @param FilterControlInterface|string|null $control Filter control instance or registered control type.
     * @param array<string, mixed> $controlConfig Control config, resolved through the control's schema.
     *
     * @throws FlareException If a control is given without an alias, the element or control cannot be resolved,
     *   a config does not satisfy its schema, or the control or value does not match the element's value class.
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
        ?string                            $source = null,
    ): Filter {
        $type = $this->resolveType($element, $source);

        if ($control !== null && !$alias)
        {
            throw new FlareException(\sprintf(
                'A filter of type "%s" with a control needs an alias: it names the form field, which must stay the'
                . ' same across requests%s.',
                $type,
                $source ? " ({$source})" : '',
            ), method: __METHOD__);
        }

        $element = $this->resolveElement($element, $source);
        $valueClass = $this->getElementValueClass($type, $element);

        $alias = $alias ?: self::generateAlias($type);

        $resolvedConfig = $this->filterOptionsResolver->resolve($element, $config, $source);

        $controlBinding = null;

        if ($control !== null)
        {
            $controlType = \is_string($control) ? $control : null;
            $control = $this->resolveControl($control, $valueClass, $source);

            $controlBinding = new ControlBinding(
                component: $control,
                type: $controlType ?? $control::class,
                config: $this->filterOptionsResolver->resolve($control, $controlConfig, $source),
            );
        }

        if ($value !== null && ($valueClass === null || !$value instanceof $valueClass))
        {
            throw new FlareException(\sprintf(
                'The value of class "%s" does not match the value class "%s" of filter element type "%s"%s.',
                $value::class,
                $valueClass ?? 'none',
                $type,
                $source ? " ({$source})" : '',
            ), method: __METHOD__, source: $source);
        }

        return new Filter(
            element: new ElementBinding($element, $type, $resolvedConfig),
            alias: $alias,
            control: $controlBinding,
            value: $value,
            targetAlias: $targetAlias,
            targetingForced: $targetingForced,
            source: $source,
        );
    }

    /**
     * Creates a filter from a stored tl_flare_filter row.
     *
     * Failure handling follows SPEC_FILTER_FORMS.md §8.1: a broken element config throws. A control
     * that cannot be resolved for the element's value class, a broken control config, or an unreadable
     * initial value fails open — a warning is logged and the filter is kept without control and value,
     * bounded only by the element's own config ({@see Filter::$controlDropped} tells it from a filter
     * configured without a control). An unreadable fixed value (no control) throws.
     *
     * @param bool $withValue False to skip reading the stored value, e.g. for backend option lists, which must
     *   load even when the value is unreadable. The control is always resolved.
     *
     * @throws FlareException If the element cannot be resolved, its config does not satisfy its schema,
     *   or — without a control — the stored value is unreadable ({@see UnreadableValueException}).
     */
    public function createFromFilterModel(FilterModel $filterModel, bool $withValue = true): Filter
    {
        $source = "{$filterModel::getTable()}.{$filterModel->id}";
        $type = $this->resolveType($filterModel->getFilterElementType(), $source);
        $element = $this->resolveElement($type, $source);

        $config = $this->filterTransformerResolver->transform($element, $type, $filterModel, $source) ?? [];

        $filter = new Filter(
            element: new ElementBinding(
                component: $element,
                type: $type,
                config: $this->filterOptionsResolver->resolve($element, $config, $source),
            ),
            alias: $filterModel->getFilterAlias(),
            targetAlias: $filterModel->getFilterTargetAlias() ?: null,
            source: $source,
        );

        $valueClass = $this->getElementValueClass($type, $element);
        $controlType = (string) $filterModel->control;

        if ($controlType === '') {
            return $withValue ? $filter->withValue($this->provideValue($valueClass, $filterModel, $element)) : $filter;
        }

        try
        {
            $control = $this->resolveControl($controlType, $valueClass, $source);

            $controlConfig = $this->filterTransformerResolver
                ->transform($control, $controlType, $filterModel, $source) ?? [];
            $controlConfig = $this->filterOptionsResolver->resolve($control, $controlConfig, $source);

            $value = $withValue ? $this->provideValue($valueClass, $filterModel, $element) : null;
        }
        catch (FlareException $e)
        {
            $this->logger->warning(\sprintf(
                '[FLARE] Filter control "%s" of filter %s is ignored, the filter applies without control and'
                . ' initial value -- [Message] %s',
                $controlType,
                $source,
                $e->getMessage(),
            ));

            return $filter->withDroppedControl();
        }

        return $filter->withControl(new ControlBinding($control, $controlType, $controlConfig))->withValue($value);
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
     * @param class-string<ValueInterface>|null $valueClass
     *
     * @throws FlareException If the stored value is unreadable or no provider exists for the value class.
     */
    private function provideValue(
        ?string                $valueClass,
        FilterModel            $filterModel,
        FilterElementInterface $element,
    ): ?ValueInterface {
        if ($valueClass === null) {
            return null;
        }

        $provider = $this->valueProviderRegistry->get($valueClass)
            ?? throw new FlareException(
                \sprintf('No value provider is registered for the value class "%s".', $valueClass),
                method: __METHOD__,
            );

        return $provider->provide($filterModel->value, $element);
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
