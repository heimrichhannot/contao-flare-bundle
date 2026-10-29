<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Form\Factory;

use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\Context\FormContextInterface;
use HeimrichHannot\FlareBundle\Event\FormHarnessBuildEvent;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\ControlScope;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Form\FilterFormInvoker;
use HeimrichHannot\FlareBundle\Form\FilterMount;
use HeimrichHannot\FlareBundle\Form\FormHarness;
use HeimrichHannot\FlareBundle\List\FilterFailure;
use HeimrichHannot\FlareBundle\List\ListSpec;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class FormHarnessFactory
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private FilterFormInvoker        $filterFormInvoker,
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
        /** @var list<FilterFailure> $failures */
        $failures = [];

        foreach ($list->filters as $filter)
        {
            $alias = $filter->alias;
            $control = $filter->control?->component;

            if ($control === null)
                // Filters without a control have no form representation.
            {
                continue;
            }

            $scope = new ControlScope($list, $filter, $context);

            try
            {
                $builder = $this->filterFormInvoker->build($scope);
            }
            catch (FilterException $e)
                // Fail closed: a control the editor chose must not be lost silently (§8.1).
            {
                $failures[] = $this->fail($list, $filter, $e);
                continue;
            }

            if ($builder === null)
                // A cancelled build, or a control without fields: never mounted.
            {
                continue;
            }

            $single = $builder->getSingle();

            try
            {
                $initial = $control->hydrate($filter->value, $scope);
            }
            catch (\Exception $e)
                // Fail open: an initial value that cannot be shown only drops the value (§8.1).
            {
                $initial = null;
                $this->dropInitialValue($filter, $e, $control::class . '::hydrate');
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

        return new FormHarness(
            $root->getForm(),
            $mounts,
            $this->translator,
            $this->logger,
            $this->debug,
            $failures,
        );
    }

    /**
     * Records a filter whose control could not build its form; it fails the list. The message names the filter and
     * its list, like the collector's.
     */
    private function fail(ListSpec $list, Filter $filter, FilterException $e): FilterFailure
    {
        $message = \sprintf(
            'Filter %s with control "%s" on %s cannot build its form: %s',
            $filter->source !== null ? "{$filter->source} (\"{$filter->alias}\")" : "\"{$filter->alias}\"",
            $filter->control?->type,
            $list->source ?? 'an inlined list',
            $e->getMessage(),
        );

        $this->logger->error('[FLARE] ' . $message . ' -- The list fails.', ['exception' => $e]);

        return new FilterFailure($filter->source, $message, $e);
    }

    /**
     * Mounts a filter without its initial value when its control cannot hydrate it. A FilterException is the control's
     * own report and only logged as a warning; any other exception is unexpected: logged as an error, and rethrown
     * wrapped in debug mode so it is not overlooked.
     *
     * @throws FilterException In debug mode, for an unexpected exception.
     */
    private function dropInitialValue(Filter $filter, \Exception $e, string $method): void
    {
        $expected = $e instanceof FilterException;
        $e = FilterException::wrap($e, $filter, $method);

        if (!$expected && $this->debug) {
            throw $e;
        }

        $this->logger->log(
            $expected ? 'warning' : 'error',
            \sprintf('Filter "%s" is mounted without its initial value: %s', $filter->alias, $e->getMessage()),
            ['alias' => $filter->alias, 'exception' => $e],
        );
    }
}
