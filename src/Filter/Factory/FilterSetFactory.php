<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Factory;

use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\Context\FormContextInterface;
use HeimrichHannot\FlareBundle\Event\FilterFormBuiltEvent;
use HeimrichHannot\FlareBundle\Event\FilterSetBuildEvent;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilder;
use HeimrichHannot\FlareBundle\Filter\FilterMount;
use HeimrichHannot\FlareBundle\Filter\FilterSet;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\Util\Str;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class FilterSetFactory
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private FilterContextFactory     $filterContextFactory,
        private FormFactoryInterface     $formFactory,
    ) {}

    /**
     * Builds the list's filter set: the root form with every mountable filter mounted onto it,
     * plus the mount↔filter map.
     *
     * @throws FlareException If the form could not be built
     */
    public function create(ListSpec $list, FormContextInterface $context): FilterSet
    {
        if (!$context instanceof ContextInterface) {
            throw new FlareException(
                'Filter form context must implement ContextInterface.',
                method: __METHOD__,
            );
        }

        $name = $context->getFormName();

        $formOptions = [
            'method'             => 'GET',
            'csrf_protection'    => false,
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

        /** @var array<string|int, FilterMount> $mounts */
        $mounts = [];

        foreach ($list->filters as $key => $filter)
        {
            if (!Str::isValidFormName($filter->alias)) {
                continue;
            }

            $filterContext = $this->filterContextFactory->create($list, $filter, $context, $key);

            // Collect-only builder: never mounted itself; its single-field spec, children,
            // attributes, and deferred listeners are transferred onto the mounted builder below.
            $builder = new FilterFormBuilder($filter->alias, null, new EventDispatcher(), $this->formFactory);
            $builder->setAttribute(FilterContext::ATTR_SELF, $filterContext);

            $filter->element->buildForm($builder, $filterContext);

            /** @var FilterFormBuiltEvent $event */
            $event = $this->eventDispatcher->dispatch(new FilterFormBuiltEvent($builder, $filterContext));

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
                    'Filter element cannot declare a single field and add children at the same time.',
                    method: __METHOD__,
                );
            }

            if ($single)
            {
                $mount = $root->create($filter->alias, $single['type'], $single['options']);
                $mount->setAttribute(FilterContext::ATTR_SINGLE_FIELD, true);
            }
            /** @mago-expect lint:no-else-clause The mount decision is a genuine either-or. */
            else
            {
                $mount = $root->create($filter->alias, FormType::class, [
                    'inherit_data' => false,
                    'label'        => false,
                    'required'     => false,
                ]);

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

            $mounts[$key] = new FilterMount($filter, $filter->alias, $filterContext);

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

        /** @var FilterSetBuildEvent $formBuildEvent */
        $formBuildEvent = $this->eventDispatcher->dispatch(new FilterSetBuildEvent(
            list: $list,
            formName: $name,
            formBuilder: $root,
        ));

        /** @var FormBuilder $root */
        $root = $formBuildEvent->formBuilder;

        return new FilterSet($root->getForm(), $mounts);
    }
}
