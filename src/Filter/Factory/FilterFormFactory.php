<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Factory;

use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\Context\FormContextInterface;
use HeimrichHannot\FlareBundle\Event\FilterElementFormBuiltEvent;
use HeimrichHannot\FlareBundle\Event\FilterFormBuildEvent;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilder;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\Util\Str;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class FilterFormFactory
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private FilterContextFactory     $filterContextFactory,
        private FormFactoryInterface     $formFactory,
    ) {}

    /**
     * @throws FlareException If the form could not be built
     */
    public function create(ListSpec $list, FormContextInterface $context): FormInterface
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

        $builder = $this->formFactory->createNamedBuilder($name, FormType::class, null, $formOptions);
        $builder->setAttribute('flare.list', $list);
        $builder->setAttribute('flare.engine_context', $context);

        foreach ($list->filters as $key => $filter)
        {
            if (!Str::isValidFormName($filter->alias)) {
                continue;
            }

            $filterContext = $this->filterContextFactory->create($list, $filter, $context, $key);

            // Collect-only builder: never mounted itself; its single-field spec, children,
            // attributes, and deferred listeners are transferred onto the mounted builder below.
            $wrapper = new FilterFormBuilder($filter->alias, null, new EventDispatcher(), $this->formFactory);
            $wrapper->setAttribute(FilterContext::ATTR_SELF, $filterContext);

            $filter->element->buildForm($wrapper, $filterContext);

            /** @var FilterElementFormBuiltEvent $event */
            $event = $this->eventDispatcher->dispatch(new FilterElementFormBuiltEvent($wrapper, $filterContext));

            if ($event->isCancelled())
                // Filters can be skipped by event listeners.
            {
                continue;
            }

            $single = $wrapper->getSingle();

            if (!$single && $wrapper->count() === 0)
                // Filters without any form representation are never mounted.
            {
                continue;
            }

            if ($single && $wrapper->count() > 0)
            {
                throw new FlareException(
                    'Filter element cannot declare a single field and add children at the same time.',
                    method: __METHOD__,
                );
            }

            if ($single)
            {
                $mount = $builder->create($filter->alias, $single['type'], $single['options']);
                $mount->setAttribute(FilterContext::ATTR_SINGLE_FIELD, true);
            }
            /** @mago-expect lint:no-else-clause The mount decision is a genuine either-or. */
            else
            {
                $mount = $builder->create($filter->alias, FormType::class, [
                    'inherit_data' => false,
                    'label'        => false,
                    'required'     => false,
                ]);

                foreach ($wrapper->all() as $childBuilder) {
                    $mount->add($childBuilder);
                }
            }

            foreach ($wrapper->getAttributes() as $attrName => $attrValue) {
                $mount->setAttribute($attrName, $attrValue);
            }

            foreach ($wrapper->getDeferredListeners() as [$eventName, $listener, $priority]) {
                $mount->addEventListener($eventName, $listener, $priority);
            }

            $builder->add($mount);
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

        /** @var FilterFormBuildEvent $formBuildEvent */
        $formBuildEvent = $this->eventDispatcher->dispatch(new FilterFormBuildEvent(
            list: $list,
            formName: $name,
            formBuilder: $builder,
        ));

        /** @var FormBuilder $builder */
        $builder = $formBuildEvent->formBuilder;

        return $builder->getForm();
    }
}
