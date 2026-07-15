<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List\Resolver;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\TransformerResolver;
use HeimrichHannot\FlareBundle\Contract\TransformerContract;

/**
 * Runs a list driver's source transformers ({@see TransformerContract}) to translate a stored
 * source (e.g. a ListModel) into canonical config values. The configured transformer map is
 * memoized per driver class.
 */
final class ListTransformerResolver
{
    /**
     * @var array<class-string, TransformerResolver>
     */
    private array $transformers = [];

    /**
     * @return array<string, mixed>|null Canonical config values, or null when no transformer matches the source.
     */
    public function transform(object $driverService, object $source): ?array
    {
        if (!isset($this->transformers[$driverService::class]))
        {
            $transformers = new TransformerResolver();

            if ($driverService instanceof TransformerContract) {
                $driverService->configureTransformers($transformers);
            }

            $this->transformers[$driverService::class] = $transformers;
        }

        if (!$transformer = $this->transformers[$driverService::class]->resolve($source)) {
            return null;
        }

        $transformer($config = new ConfigBuilder(), $source);

        return $config->all();
    }
}
