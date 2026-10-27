<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\UndefinedConfigKeyError;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\List\ListSpec;

/**
 * The value-free situation a filter control hook runs in: the list, the filter, and the engine context.
 * {@see get()} reads the control's own config; {@see forElement()} hands the element its own scope.
 *
 * @api
 */
final readonly class ControlScope
{
    /** Attribute-bag key under which this scope is stored on the per-filter form builder. */
    public const ATTR_SELF = 'flare.filter_scope';

    /** Attribute-bag key marking a root form child as a flat-mounted single field. */
    public const ATTR_SINGLE_FIELD = 'flare.single_field';

    /**
     * @throws \InvalidArgumentException If the filter has no control.
     */
    public function __construct(
        public ListSpec         $list,
        public Filter           $filter,
        public ContextInterface $engineContext,
    ) {
        if ($filter->control === null) {
            throw new \InvalidArgumentException(\sprintf('The filter "%s" has no control.', $filter->alias));
        }
    }

    /**
     * The control's own config value.
     *
     * @throws UndefinedConfigKeyError If the control's schema does not define the key.
     */
    public function get(string $key): mixed
    {
        return $this->controlBinding()->config->get($key);
    }

    /**
     * Whether the control's own config holds a value for the key.
     */
    public function has(string $key): bool
    {
        return $this->controlBinding()->config->has($key);
    }

    /**
     * The filter's element. With a contract, the element as that contract — use it to reach a capability port
     * ({@see \HeimrichHannot\FlareBundle\Contract\FilterElement\ChoiceSourceContract},
     * {@see \HeimrichHannot\FlareBundle\Contract\FilterElement\DateBoundsContract}) the control requires.
     *
     * @template T of object
     * @param class-string<T>|null $contract
     * @return ($contract is null ? FilterElementInterface : T)
     *
     * @throws FilterException If the element does not implement the contract.
     */
    public function element(?string $contract = null): object
    {
        $element = $this->filter->element->component;

        if ($contract === null || $element instanceof $contract) {
            return $element;
        }

        throw new FilterException(\sprintf(
            'The control "%s" requires an element implementing %s, got %s.',
            $this->controlBinding()->type,
            $contract,
            \get_debug_type($element),
        ));
    }

    /**
     * The element as the given contract, or null if it does not implement it — for a capability port the
     * control can do without.
     *
     * @template T of object
     * @param class-string<T> $contract
     * @return T|null
     */
    public function tryElement(string $contract): ?object
    {
        $element = $this->filter->element->component;

        return $element instanceof $contract ? $element : null;
    }

    /**
     * The element's scope over the same list, filter and engine context, to hand to element contracts
     * ({@see \HeimrichHannot\FlareBundle\Contract\FilterElement\ChoiceSourceContract},
     * {@see \HeimrichHannot\FlareBundle\Contract\FilterElement\DateBoundsContract}).
     */
    public function forElement(): ElementScope
    {
        return new ElementScope($this->list, $this->filter, $this->engineContext);
    }

    private function controlBinding(): ControlBinding
    {
        /** @var ControlBinding Guaranteed by the constructor. */
        return $this->filter->control;
    }
}
