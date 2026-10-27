<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Form\Factory;

use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\Context\FormContextInterface;
use HeimrichHannot\FlareBundle\Event\FilterControlBuildFormEvent;
use HeimrichHannot\FlareBundle\Event\FormHarnessBuildEvent;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\ControlScope;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilder;
use HeimrichHannot\FlareBundle\Form\FilterMount;
use HeimrichHannot\FlareBundle\Form\FormHarness;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\Util\Str;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class FormHarnessFactory
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private FormFactoryInterface     $formFactory,
        private LoggerInterface          $logger,
        private TranslatorInterface      $translator,
        private bool                     $debug = false,
    ) {}

    /**
     * Builds the list's filter set: the root form with every mountable filter mounted onto it,
     * plus the mount↔filter map.
     *
     * @throws FlareException If the form could not be built
     */
    public function create(ListSpec $list, FormContextInterface $context): FormHarness
    {
        if (!$context instanceof ContextInterface) {
            throw new FlareException(
                'Filter form context must implement ContextInterface.',
                method: __METHOD__,
            );
        }

        $name = $context->getFormName();

        $formOptions = [
            'method' => 'GET',
            'csrf_protection' => false,
            'translation_domain' => 'flare_form',
            'attr' => [
                'data-flare-form' => 'keep-query',
            ],
        ];

        if ($action = $context->createFormActionUrl()) {
            $formOptions['action'] = $action;
        }

        $root = $this->formFactory->createNamedBuilder($name, FormType::class, null, $formOptions);
        $root->setAttribute('flare.list', $list);
        $root->setAttribute('flare.engine_context', $context);

        /** @var array<string, FilterMount> $mounts */
        $mounts = [];

        foreach ($list->filters as $filter)
        {
            $alias = $filter->alias;
            $control = $filter->control?->component;

            if ($control === null || !Str::isValidFormName($alias))
                // Filters without a control have no form representation.
            {
                continue;
            }

            $scope = new ControlScope($list, $filter, $context);

            // Collect-only builder: never mounted itself; its single-field spec, children,
            // attributes, and deferred listeners are transferred onto the mounted builder below.
            $builder = new FilterFormBuilder($alias, null, new EventDispatcher(), $this->formFactory);
            $builder->setAttribute(ControlScope::ATTR_SELF, $scope);

            try
            {
                $control->buildForm($builder, $scope);
            }
            catch (\Exception $e)
                // Fail open: a control that cannot build drops its filter, not the list (§8.1).
            {
                $this->skip($filter, $e, $control::class . '::buildForm');
                continue;
            }

            /** @var FilterControlBuildFormEvent $event */
            $event = $this->eventDispatcher->dispatch(new FilterControlBuildFormEvent($builder, $scope));

            if ($event->isCancelled())
                // Filters can be skipped by event listeners.
            {
                continue;
            }

            $single = $builder->getSingle();

            if (!$single && $builder->count() === 0)
                // Filters without any form representation are never mounted.
            {
                continue;
            }

            if ($single && $builder->count() > 0)
            {
                throw new FlareException(
                    'Filter control cannot declare a single field and add children at the same time.',
                    method: __METHOD__,
                );
            }

            try
            {
                $initial = $control->hydrate($filter->value, $scope);
            }
            catch (\Exception $e)
            {
                $this->skip($filter, $e, $control::class . '::hydrate');
                continue;
            }

            if ($single)
            {
                $options = $single['options'];

                if ($initial !== null) {
                    $options['data'] = $initial;
                }

                $mount = $root->create($alias, $single['type'], $options);
                $mount->setAttribute(ControlScope::ATTR_SINGLE_FIELD, true);
            }
            /** @mago-expect lint:no-else-clause The mount decision is a genuine either-or. */
            else
            {
                $options = [
                    'inherit_data' => false,
                    'label'        => false,
                    'required'     => false,
                    ...$builder->getCompoundOptions(),
                ];

                if ($initial !== null) {
                    $options['data'] = $initial;
                }

                $mount = $root->create($alias, FormType::class, $options);

                foreach ($builder->all() as $childBuilder) {
                    $mount->add($childBuilder);
                }
            }

            foreach ($builder->getAttributes() as $attrName => $attrValue) {
                $mount->setAttribute($attrName, $attrValue);
            }

            foreach ($builder->getDeferredListeners() as [$eventName, $listener, $priority]) {
                $mount->addEventListener($eventName, $listener, $priority);
            }

            $mounts[$alias] = new FilterMount($filter, $alias, $scope);

            $root->add($mount);
        }

        /*
         * **Always add submit buttons in templates, not in the form builder!**
         * This is NOT advised:
         * ```php
         *  if ($builder->count()) {
         *      $builder->add('submit', SubmitType::class, [ 'label' => 'submit']);
         *  }
         * ```
         */

        /** @var FormHarnessBuildEvent $formBuildEvent */
        $formBuildEvent = $this->eventDispatcher->dispatch(new FormHarnessBuildEvent(
            list: $list,
            formName: $name,
            formBuilder: $root,
        ));

        /** @var FormBuilder $root */
        $root = $formBuildEvent->formBuilder;

        return new FormHarness($root->getForm(), $mounts, $this->translator, $this->logger, $this->debug);
    }

    /**
     * Drops a filter whose control failed. A FilterException is the control's own report and only logged; any other
     * exception is unexpected: logged as an error, and rethrown wrapped in debug mode so it is not overlooked.
     *
     * @throws FilterException In debug mode, for an unexpected exception.
     */
    private function skip(Filter $filter, \Exception $e, string $method): void
    {
        $expected = $e instanceof FilterException;
        $e = FilterException::wrap($e, $filter, $method);

        if (!$expected && $this->debug) {
            throw $e;
        }

        $this->logger->log(
            $expected ? 'warning' : 'error',
            \sprintf('Filter "%s" is not mounted, its control failed: %s', $filter->alias, $e->getMessage()),
            ['alias' => $filter->alias, 'exception' => $e],
        );
    }
}
