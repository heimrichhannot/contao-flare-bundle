<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\OptionsResolver;

use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementOptionsInterface;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Resolves a filter's canonical config through the element's declared schema.
 * Elements without a {@see FilterElementOptionsInterface} receive their config verbatim (unvalidated).
 */
class FilterOptionsResolver
{
    /**
     * @var array<class-string, OptionsResolver>
     */
    private array $resolvers = [];

    /**
     * @return array<string, mixed>
     *
     * @throws FilterException If the config does not satisfy the element's schema.
     */
    public function resolve(Filter $filter, FilterElementInterface $element): array
    {
        if (!$element instanceof FilterElementOptionsInterface) {
            return $filter->config;
        }

        if (!isset($this->resolvers[$element::class]))
        {
            $resolver = new OptionsResolver();
            $element->configureOptions($resolver);
            $this->resolvers[$element::class] = $resolver;
        }

        try
        {
            return $this->resolvers[$element::class]->resolve($filter->config);
        }
        catch (\Throwable $e)
        {
            throw new FilterException(
                \sprintf('[FLARE] Invalid filter config for element "%s": %s', $element::class, $e->getMessage()),
                previous: $e,
                method: $element::class . '::configureOptions',
                source: $filter->source,
            );
        }
    }
}
