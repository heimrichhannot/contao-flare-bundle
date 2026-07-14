<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Resolver;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\TransformerBuilder;
use HeimrichHannot\FlareBundle\Contract\TransformerContract;
use HeimrichHannot\FlareBundle\Event\FilterTransformerEvent;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Runs an element's source transformers ({@see TransformerContract}) to translate a stored
 * source (e.g. a FilterModel) into canonical config values. The configured transformer map
 * is memoized per element class; listeners extend it via {@see FilterTransformerEvent}.
 */
class FilterTransformerResolver
{
    /**
     * @var array<class-string, TransformerBuilder>
     */
    private array $builders = [];

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * @return array<string, mixed>|null Canonical config values, or null when no transformer matches the source.
     */
    public function transform(FilterElementInterface $element, ?string $elementType, object $source): ?array
    {
        if (!isset($this->builders[$element::class]))
        {
            $transformers = new TransformerBuilder();

            if ($element instanceof TransformerContract) {
                $element->configureTransformers($transformers);
            }

            $this->eventDispatcher->dispatch(new FilterTransformerEvent($transformers, $element, $elementType));

            $this->builders[$element::class] = $transformers;
        }

        if (!$transformer = $this->builders[$element::class]->resolve($source)) {
            return null;
        }

        $transformer($source, $config = new ConfigBuilder());

        return $config->all();
    }
}
