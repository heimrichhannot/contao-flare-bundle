<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Contract\ListType\BuildListContract;
use HeimrichHannot\FlareBundle\Event\ListBuildEvent;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\List\Resolver\ListOptionsResolver;
use HeimrichHannot\FlareBundle\List\Resolver\ListTransformerResolver;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\Model\ListModel;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Configures a list and its filters, then builds the immutable {@see ListSpec}.
 *
 * Build order: the type's {@see BuildListContract::buildList()} hook, the
 * {@see ListBuildEvent} (named dispatch `flare.list.{type}.build`), then config assembly —
 * base translation, the type's model transformers, and explicit {@see set()} overrides —
 * resolved through the base and type schemas.
 */
final class ListSpecBuilder implements ListSpecBuilderInterface
{
    /**
     * @var array<string, Filter>
     */
    private array $filters = [];

    /**
     * @var array<string, mixed>
     */
    private array $overrides = [];

    private int $generatedFilterKeys = 0;

    public function __construct(
        private readonly ListOptionsResolver      $optionsResolver,
        private readonly ListTransformerResolver  $transformerResolver,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ListDriverReference      $driverReference,
        private readonly string                   $dc,
        private readonly ?ListModel               $model = null,
        private readonly ?string                  $source = null,
    ) {}

    public function getDriverReference(): ListDriverReference
    {
        return $this->driverReference;
    }

    public function getType(): string
    {
        return $this->driverReference->type;
    }

    public function getDc(): string
    {
        return $this->dc;
    }

    public function getModel(): ?ListModel
    {
        return $this->model;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    /**
     * Sets a canonical config value, overriding base translation and type transformers.
     */
    public function set(string $key, mixed $value): self
    {
        $this->overrides[$key] = $value;

        return $this;
    }

    /**
     * Adds a filter. The key defaults to the filter's alias; alias-less filters receive a generated key.
     */
    public function addFilter(Filter $filter, ?string $key = null): self
    {
        $key ??= $filter->alias ?? ('_generated_' . $this->generatedFilterKeys++);
        $this->filters[$key] = $filter;

        return $this;
    }

    public function removeFilter(string $key): self
    {
        unset($this->filters[$key]);

        return $this;
    }

    /**
     * @return array<string, Filter>
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    public function getFilter(string $key): ?Filter
    {
        return $this->filters[$key] ?? null;
    }

    public function hasFilterOfType(string $elementType): bool
    {
        foreach ($this->filters as $filter)
        {
            if ($filter->type === $elementType) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws FlareException If the resulting config does not satisfy the schema.
     */
    public function build(): ListSpec
    {
        $driver = $this->driverReference->driver;

        if ($driver instanceof BuildListContract) {
            $driver->buildList($this);
        }

        $this->eventDispatcher->dispatch(new ListBuildEvent($this));

        $config = new ConfigBuilder();

        if ($this->model)
        {
            BaseListOptions::transform($config, $this->model);

            $transformed = $this->transformerResolver->transform(
                $driver,
                $this->getType(),
                $this->model,
            );

            foreach ($transformed ?? [] as $key => $value) {
                $config->set($key, $value);
            }
        }

        foreach ($this->overrides as $key => $value) {
            $config->set($key, $value);
        }

        return new ListSpec(
            reference: $this->driverReference,
            dc: $this->dc,
            filters: $this->filters,
            config: $this->optionsResolver->resolve($driver, $config->all(), $this->source),
            source: $this->source,
        );
    }
}
