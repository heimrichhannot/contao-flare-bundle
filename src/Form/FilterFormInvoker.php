<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Form;

use HeimrichHannot\FlareBundle\Event\FilterControlBuildFormEvent;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Exception\UndefinedConfigKeyError;
use HeimrichHannot\FlareBundle\Filter\ControlScope;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilder;
use HeimrichHannot\FlareBundle\Util\Str;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Builds one controlled filter's form: the control's buildForm() and the {@see FilterControlBuildFormEvent}. Shared by
 * the {@see Factory\FormHarnessFactory} and the backend's check for broken filters, so both judge a control alike.
 *
 * @internal
 */
final readonly class FilterFormInvoker
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private FormFactoryInterface     $formFactory,
    ) {}

    /**
     * The collect-only builder holding the control's fields, or null if the filter has no form representation: the
     * event was cancelled, or the control declared no field.
     *
     * A control that cannot build its form fails the list: any exception of the control, a FlareException of a
     * listener, an alias that is no valid form name, and a single field declared together with children. Any other
     * exception of a listener is a bug and escapes, as do \Errors.
     *
     * @throws FilterException
     * @throws UndefinedConfigKeyError If the control reads a config key its schema does not define.
     */
    public function build(ControlScope $scope): ?FilterFormBuilder
    {
        $filter = $scope->filter;
        $control = $scope->filter->control?->component;
        \assert($control !== null, 'A control scope always has a control.');

        $method = $control::class . '::buildForm';

        if (!Str::isValidFormName($filter->alias)) {
            throw FilterException::wrap(
                new FilterException(\sprintf('The alias "%s" is not a valid form name.', $filter->alias)),
                $filter,
                __METHOD__,
            );
        }

        // Collect-only builder: never mounted itself; its single-field spec, children, attributes, and deferred
        // listeners are transferred onto the mounted builder by the harness.
        $builder = new FilterFormBuilder($filter->alias, null, new EventDispatcher(), $this->formFactory);
        $builder->setAttribute(ControlScope::ATTR_SELF, $scope);

        try
        {
            $control->buildForm($builder, $scope);
        }
        catch (\Exception $e)
        {
            throw FilterException::wrap($e, $filter, $method);
        }

        try
        {
            /** @var FilterControlBuildFormEvent $event */
            $event = $this->eventDispatcher->dispatch(new FilterControlBuildFormEvent($builder, $scope));
        }
        catch (FlareException $e)
        {
            throw FilterException::wrap($e, $filter, FilterControlBuildFormEvent::class);
        }

        if ($event->isCancelled()) {
            return null;
        }

        $single = $builder->getSingle();

        if (!$single && $builder->count() === 0) {
            return null;
        }

        if ($single && $builder->count() > 0) {
            throw FilterException::wrap(
                new FilterException('Filter control cannot declare a single field and add children at the same time.'),
                $filter,
                $method,
            );
        }

        return $builder;
    }
}
