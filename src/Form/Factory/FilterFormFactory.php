<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Form\Factory;

use Contao\PageModel;
use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\Context\Interface\FormContextInterface;
use HeimrichHannot\FlareBundle\Event\FilterElementFormBuiltEvent;
use HeimrichHannot\FlareBundle\Event\FilterFormBuildEvent;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\OptionsResolver\FilterOptionsResolver;
use HeimrichHannot\FlareBundle\Registry\FilterElementResolver;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use HeimrichHannot\FlareBundle\Util\Str;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class FilterFormFactory
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private FilterOptionsResolver    $filterConfigResolver,
        private FilterElementResolver    $filterElementResolver,
        private FormFactoryInterface     $formFactory,
    ) {}

    /**
     * @throws FlareException If the form could not be built
     */
    public function create(ListSpecification $list, FormContextInterface $context): FormInterface
    {
        if (!$context instanceof ContextInterface) {
            throw new FlareException('Filter form context must implement ContextInterface.', method: __METHOD__);
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

        if ($action = $this->resolveFormAction($context)) {
            $formOptions['action'] = $action;
        }

        $builder = $this->formFactory->createNamedBuilder($name, FormType::class, null, $formOptions);
        $builder->setAttribute('flare.list', $list);
        $builder->setAttribute('flare.engine_context', $context);

        foreach ($list->getFilters() as $key => $filter)
        {
            if (!Str::isValidFormName($filter->alias)) {
                continue;
            }

            if (!$element = $this->filterElementResolver->resolve($filter)) {
                continue;
            }

            $filterContext = new FilterContext(
                list: $list,
                filter: $filter,
                config: $this->filterConfigResolver->resolve($filter, $element),
                engineContext: $context,
                key: $key,
            );

            $child = $builder->create($filter->alias, FormType::class, [
                'inherit_data' => false,
                'label'        => false,
                'required'     => false,
            ]);
            $child->setAttribute(FilterContext::FORM_ATTRIBUTE, $filterContext);

            $element->buildForm($child, $filterContext);

            /** @var FilterElementFormBuiltEvent $event */
            $event = $this->eventDispatcher->dispatch(new FilterElementFormBuiltEvent($child, $filterContext));

            if ($event->isCancelled() || $child->count() === 0)
                // Empty compound children are never mounted.
            {
                continue;
            }

            $builder->add($child);
        }

        /*
         * **Always add submit buttons in templates, not in the form builder!**
         * This is not advised:
         * ```php
         *  if ($builder->count()) {
         *      $builder->add('submit', SubmitType::class, [
         *      'label' => 'submit',
         *  ]);
         * ```
         */

        /** @var FilterFormBuildEvent $formBuildEvent */
        $formBuildEvent = $this->eventDispatcher->dispatch(new FilterFormBuildEvent(
            listSpecification: $list,
            formName: $name,
            formBuilder: $builder,
        ));

        $builder = $formBuildEvent->formBuilder;

        return $builder->getForm();
    }

    private function resolveFormAction(FormContextInterface $config): ?string
    {
        if (!$jumpTo = $config->getFormActionPage()) {
            return null;
        }

        if (!$pageModel = PageModel::findByPk($jumpTo)) {
            return null;
        }

        return $pageModel->getAbsoluteUrl();
    }
}
