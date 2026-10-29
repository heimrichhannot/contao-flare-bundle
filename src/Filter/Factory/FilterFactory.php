<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Factory;

use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\DependencyInjection\Compiler\ValueClassReflector;
use HeimrichHannot\FlareBundle\Exception\FilterException;
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
     * @throws FlareException If a control is given without an alias or with one that is no valid form name, the
     *   element or control cannot be resolved, a config does not satisfy its schema, or the control or value does not
     *   match the element's value class.
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
     * Failure handling follows SPEC_FILTER_FORMS.md §8.1: a broken element config throws, and so does a stored
     * control that cannot be resolved for the element's value class or whose config is broken — the editor chose
     * it, and the filter must not apply without it. An unreadable initial value only drops the value (warning);
     * an unreadable fixed value (no control) throws.
     *
     * @param bool $withValue False to skip reading the stored value, e.g. for backend option lists, which must
     *   load even when the value is unreadable. The control is always resolved.
     *
     * @throws FlareException If the element or the stored control cannot be resolved, either config does not satisfy
     *   its schema, or — without a control — the stored value is unreadable ({@see UnreadableValueException}).
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

        // Without the value, the filter serves the backend form, which must load to repair a legacy alias.
        if ($withValue) {
            self::assertFormName($filter->alias, $source);
        }

        $control = $this->resolveControl($controlType, $valueClass, $source);

        $controlConfig = $this->filterTransformerResolver->transform($control, $controlType, $filterModel, $source) ?? [];
        $controlConfig = $this->filterOptionsResolver->resolve($control, $controlConfig, $source);

        $filter = $filter->withControl(new ControlBinding($control, $controlType, $controlConfig));

        return $withValue ? $filter->withValue($this->provideInitialValue($valueClass, $filterModel, $element)) : $filter;
    }

    /**
     * The initial value of a filter with a control: unreadable — any {@see FilterException} from the value provider,
     * as its contract promises — it is dropped with a warning, keeping the control (SPEC_FILTER_FORMS.md §8.1).
     *
     * @param class-string<ValueInterface>|null $valueClass
     *
     * @throws FlareException If no provider exists for the value class.
     */
    private function provideInitialValue(
        ?string                $valueClass,
        FilterModel            $filterModel,
        FilterElementInterface $element,
    ): ?ValueInterface {
        try
        {
            return $this->provideValue($valueClass, $filterModel, $element);
        }
        catch (FilterException $e)
        {
            $this->logger->warning(\sprintf(
                '[FLARE] Initial value of filter %s.%s is ignored -- [Message] %s',
                $filterModel::getTable(),
                $filterModel->id,
                $e->getMessage(),
            ));
        }

        return null;
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
     * Value providers are handled like filter hooks: a FilterException is the provider's report, any other exception
     * is unexpected, logged as an error and wrapped. A FlareException that is no FilterException (a wiring error, e.g.
     * an element lacking the contract its provider needs) passes unwrapped, so it is never mistaken for an unreadable
     * value.
     *
     * @param class-string<ValueInterface>|null $valueClass
     *
     * @throws FilterException If the stored value is unreadable or the provider fails.
     * @throws FlareException If no provider exists for the value class, or the provider reports a wiring error.
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

        try
        {
            return $provider->provide($filterModel->value, $element);
        }
        catch (FlareException $e)
        {
            throw $e;
        }
        // @phpstan-ignore catch.neverThrown (userland providers may break their contract)
        catch (\Exception $e)
        {
            $source = "{$filterModel::getTable()}.{$filterModel->id}";

            $this->logger->error(\sprintf(
                '[FLARE] The value provider %s failed unexpectedly for filter %s: %s',
                $provider::class,
                $source,
                $e->getMessage(),
            ), ['exception' => $e]);

            throw new FilterException(
                $e->getMessage(),
                $e->getCode(),
                $e,
                method: $provider::class . '::provide',
                source: $source,
            );
        }
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
