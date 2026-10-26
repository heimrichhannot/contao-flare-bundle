<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List;

use HeimrichHannot\FlareBundle\Contract\ListDriver\BuildListContract;
use HeimrichHannot\FlareBundle\Event\ListDriverBuildListEvent;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\Factory\ListSpecFactory;
use HeimrichHannot\FlareBundle\List\Resolver\ListDriverResolver;
use HeimrichHannot\FlareBundle\Model\ListModel;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Configures a list and its filters, then builds the immutable {@see ListSpec}.
 *
 * Build order: the driver's {@see BuildListContract::buildList()} hook, the
 * {@see ListDriverBuildListEvent} (named dispatch `flare.list_driver.{type}.build_list`), then config assembly —
 * base translation, the driver's model transformers, and explicit {@see set()} overrides —
 * handed to {@see ListSpecFactory} for schema resolution and construction.
 */
final class ListSpecBuilder implements ListSpecBuilderInterface
{
    /**
     * @var array<string, Filter>
     */
    private array $filters = [];

    /**
     * @var list<string>
     */
    private array $failures = [];

    /**
     * @var array<string, mixed>
     */
    private array $overrides = [];

    public function __construct(
        private readonly ListDriverResolver         $listDriverResolver,
        private readonly ListSpecFactory            $specFactory,
        private readonly EventDispatcherInterface   $eventDispatcher,
        private readonly ListDriverInterface|string $driver,
        private readonly ?ListModel                 $model = null,
        private readonly ?string                    $source = null,
    ) {}

    public function getDriver(): ListDriverInterface|string
    {
        return $this->driver;
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
     * Sets a canonical config value, overriding base translation and driver transformers.
     */
    public function set(string $key, mixed $value): self
    {
        $this->overrides[$key] = $value;

        return $this;
    }

    /**
     * Adds a filter. The key defaults to the filter's alias.
     *
     * A filter is never replaced silently: replacing a filter without a control would widen the list
     * (SPEC_FILTER_FORMS.md §8.1). Remove it first with {@see removeFilter()}.
     *
     * @throws FlareException If the key is already taken, or another filter already has the alias.
     */
    public function addFilter(Filter $filter, ?string $key = null): self
    {
        $key ??= $filter->alias;

        $aliasTaken = \array_filter(
            $this->filters,
            static fn (Filter $existing): bool => $existing->alias === $filter->alias,
        );

        if (isset($this->filters[$key]) || $aliasTaken)
        {
            throw new FlareException(
                \sprintf(
                    'The list already has a filter with the key "%s" or the alias "%s".',
                    $key,
                    $filter->alias,
                ),
                method: __METHOD__,
                source: $filter->source ?? $this->source,
            );
        }

        $this->filters[$key] = $filter;

        return $this;
    }

    /**
     * Records the failure of a filter that fails the list as a whole (SPEC_FILTER_FORMS.md §8.1).
     */
    public function addFailure(string $failure): self
    {
        $this->failures[] = $failure;

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

    /**
     * @param class-string<FilterElementInterface> $class
     */
    public function hasFilterInstance(string $class): bool
    {
        foreach ($this->filters as $filter)
        {
            if ($filter->element instanceof $class) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws FlareException If the resulting config does not satisfy the schema or no data
     *                        container can be determined.
     */
    public function build(): ListSpec
    {
        $driver = $this->listDriverResolver->resolve($this->driver);
        if ($driver->driver instanceof BuildListContract) {
            $driver->driver->buildList($this);
        }

        $this->eventDispatcher->dispatch(new ListDriverBuildListEvent($this));

        $spec = $this->model
            ? $this->specFactory->createFromListModel(
                listModel: $this->model,
                driver: $driver,
                filters: $this->filters,
                config: $this->overrides,
                source: $this->source,
            )
            : $this->specFactory->create(
                driver: $driver,
                filters: $this->filters,
                config: $this->overrides,
                source: $this->source,
            );

        foreach ($this->failures as $failure) {
            $spec = $spec->withFailure($failure);
        }

        return $spec;
    }
}
