<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Exception\UndefinedConfigKeyError;
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
