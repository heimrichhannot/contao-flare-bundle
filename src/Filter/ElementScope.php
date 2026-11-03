<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Exception\UndefinedConfigKeyError;
use HeimrichHannot\FlareBundle\List\ListSpec;

/**
 * The value-free situation a filter element hook runs in: the list, the filter, and the engine context.
 * {@see get()} reads the element's own config.
 *
 * @api
 */
final readonly class ElementScope
{
    public function __construct(
        public ListSpec         $list,
        public Filter           $filter,
        public ContextInterface $engineContext,
    ) {}

    /**
     * The element's own config value.
     *
     * @throws UndefinedConfigKeyError If the element's schema does not define the key.
     */
    public function get(string $key): mixed
    {
        return $this->filter->element->config->get($key);
    }

    /**
     * Whether the element's own config holds a value for the key.
     */
    public function has(string $key): bool
    {
        return $this->filter->element->config->has($key);
    }
}
