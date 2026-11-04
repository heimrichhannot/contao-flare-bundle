<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contao\Factory;

use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Exception\UnreadableValueException;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterCompoundFactory as CoreFilterCompoundFactory;
use HeimrichHannot\FlareBundle\Filter\FilterCompound;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Registry\ValueProviderRegistry;
use Psr\Log\LoggerInterface;

/**
 * Creates {@see FilterCompound} DTOs from stored tl_flare_filter rows, on top of the model-free
 * {@see CoreFilterCompoundFactory::create()}.
 */
final readonly class FilterCompoundFactory
{
    public function __construct(
        private CoreFilterCompoundFactory $filterCompoundFactory,
        private ValueProviderRegistry     $valueProviderRegistry,
        private LoggerInterface           $logger,
    ) {}

    /**
     * Creates a filter from a stored tl_flare_filter row.
     *
     * Failure handling: a broken element config throws, and so does a stored control that cannot be resolved for
     * the element's value class or whose config is broken — the editor chose it, and the filter must not apply
     * without it. So does a controlled filter whose alias is no valid form name, with or without the value: a legacy
     * alias must be renamed. An unreadable initial value only drops the value (warning); an unreadable fixed value
     * (no control) throws.
     *
     * @param bool $withValue False to skip reading the stored value, e.g. for the backend context and option lists,
     *   which must load even when the value is unreadable. The control is always resolved.
     *
     * @throws FlareException If the element or the stored control cannot be resolved, either config does not satisfy
     *   its schema, the alias of a controlled filter is no valid form name, or — without a control — the stored value
     *   is unreadable ({@see UnreadableValueException}).
     */
    public function create(FilterModel $model, bool $withValue = true): FilterCompound
    {
        $source = "{$model::getTable()}.{$model->id}";
        $control = (string) $model->control ?: null;

        $filter = $this->filterCompoundFactory->create(
            element: $model->getFilterElementType(),
            alias: $model->getFilterAlias(),
            control: $control,
            targetAlias: $model->getFilterTargetAlias() ?: null,
            configSource: $model,
            source: $source,
        );

        if (!$withValue) {
            return $filter;
        }

        return $filter->withValue($control === null
            ? $this->provideValue($filter, $model)
            : $this->provideInitialValue($filter, $model));
    }

    /**
     * The initial value of a filter with a control: unreadable — any {@see FilterException} from the value provider,
     * as its contract promises — it is dropped with a warning, keeping the control.
     *
     * @throws FlareException If no provider exists for the value class.
     */
    private function provideInitialValue(FilterCompound $filter, FilterModel $model): ?ValueInterface
    {
        try
        {
            return $this->provideValue($filter, $model);
        }
        catch (FilterException $e)
        {
            $this->logger->warning(\sprintf(
                '[FLARE] Initial value of filter %s.%s is ignored -- [Message] %s',
                $model::getTable(),
                $model->id,
                $e->getMessage(),
            ));
        }

        return null;
    }

    /**
     * Value providers are handled like filter hooks: a FilterException is the provider's report, any other exception
     * is unexpected, logged as an error and wrapped. A FlareException that is no FilterException (a wiring error, e.g.
     * an element lacking the contract its provider needs) passes unwrapped, so it is never mistaken for an unreadable
     * value.
     *
     * @throws FilterException If the stored value is unreadable or the provider fails.
     * @throws FlareException If no provider exists for the value class, or the provider reports a wiring error.
     */
    private function provideValue(FilterCompound $filter, FilterModel $model): ?ValueInterface
    {
        if (!$valueClass = $filter->element->valueClass) {
            return null;
        }

        $provider = $this->valueProviderRegistry->get($valueClass)
            ?? throw new FlareException(
                \sprintf('No value provider is registered for the value class "%s".', $valueClass),
                method: __METHOD__,
            );

        try
        {
            return $provider->provide($model->value, $filter->element->component);
        }
        catch (FlareException $e)
        {
            throw $e;
        }
        // @phpstan-ignore catch.neverThrown (userland providers may break their contract)
        catch (\Exception $e)
        {
            $source = "{$model::getTable()}.{$model->id}";

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
}
