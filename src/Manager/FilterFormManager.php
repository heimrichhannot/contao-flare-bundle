<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Manager;

use Contao\PageModel;
use HeimrichHannot\FlareBundle\Context\Interface\FormContextInterface;
use HeimrichHannot\FlareBundle\Contract\FilterElement\FormTypeOptionsContract;
use HeimrichHannot\FlareBundle\Contract\FilterElement\HydrateFormContract;
use HeimrichHannot\FlareBundle\Event\FilterElementFormTypeOptionsEvent;
use HeimrichHannot\FlareBundle\Event\FilterFormBuildEvent;
use HeimrichHannot\FlareBundle\Event\FilterFormChildOptionsEvent;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterDefinition;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use Symfony\Component\Form\Exception\OutOfBoundsException;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class FilterFormManager
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private FilterElementRegistry    $filterElementRegistry,
        private FormFactoryInterface     $formFactory,
    ) {}

    /**
     * @throws FilterException If the form could not be built
     */
    public function buildForm(ListSpecification $listSpecification, FormContextInterface $context): FormInterface
    {
        $name = $context->getFormName();
        $filters = $listSpecification->getFilters();

        $formOptions = [
            'method'             => 'GET',
            'csrf_protection'    => false,
            'translation_domain' => 'flare_form',
            'attr' => [
                'data-flare-keep-query' => 'true',
            ],
        ];

        if ($action = $this->getFormAction($context)) {
            $formOptions['action'] = $action;
        }

        $builder = $this->formFactory->createNamedBuilder($name, FormType::class, null, $formOptions);

        foreach ($filters->getIterator() as $filterDefinition)
            // Apply only non-intrinsic, published filters with a valid type
        {
            if (!$filterDefinition->getType() || $filterDefinition->isIntrinsic()) {
                continue;
            }

            if (!$formType = $this->filterElementRegistry->get($filterDefinition->getType())?->getFormType()) {
                continue;
            }

            $options = $this->getFilterElementOptions($listSpecification, $filterDefinition);

            $childName = $filterDefinition->getFilterFormFieldName();

            /** @var FilterFormChildOptionsEvent $childOptionsEvent */
            $childOptionsEvent = $this->eventDispatcher->dispatch(new FilterFormChildOptionsEvent(
                listSpecification: $listSpecification,
                filterDefinition: $filterDefinition,
                parentFormName: $name,
                formName: $childName,
                options: $options,
            ));

            $options = $childOptionsEvent->options;

            $builder->add($childName, $formType, $options);
        }

        /*
         * **Always add submit buttons in templates, not in the form builder!**
         *
         * ```php
         *  if ($builder->count()) {
         *      $builder->add('submit', SubmitType::class, [
         *      'label' => 'submit',
         *  ]);
         * ```
         */

        /** @var FilterFormBuildEvent $formBuildEvent */
        $formBuildEvent = $this->eventDispatcher->dispatch(new FilterFormBuildEvent(
            listSpecification: $listSpecification,
            formName: $name,
            formBuilder: $builder,
        ));

        $builder = $formBuildEvent->formBuilder;

        return $builder->getForm();
    }

    public function getFormAction(FormContextInterface $config): ?string
    {
        if (!$jumpTo = $config->getFormActionPage()) {
            return null;
        }

        if (!$pageModel = PageModel::findByPk($jumpTo)) {
            return null;
        }

        return $pageModel->getAbsoluteUrl();
    }

    /**
     * @param ListSpecification $listSpecification
     * @param FilterDefinition $filterDefinition
     * @return array
     * @throws FilterException
     */
    private function getFilterElementOptions(
        ListSpecification $listSpecification,
        FilterDefinition  $filterDefinition,
    ): array {
        $formTypeOptionsEvent = new FilterElementFormTypeOptionsEvent(
            listDefinition: $listSpecification,
            filterDefinition: $filterDefinition,
            options: [],
        );

        $filterElement = $this->filterElementRegistry->get($filterDefinition->getType())?->getService();
        if ($filterElement instanceof FormTypeOptionsContract)
        {
            $filterElement->onFormTypeOptionsEvent($formTypeOptionsEvent);
        }

        /** @var FilterElementFormTypeOptionsEvent $formTypeOptionsEvent */
        $formTypeOptionsEvent = $this->eventDispatcher->dispatch($formTypeOptionsEvent);

        if ($formTypeOptionsEvent->isChoicesBuilderEnabled())
        {
            $choicesBuilder = $formTypeOptionsEvent->getChoicesBuilder();

            $choicesOptions = [
                'choices' => $choicesBuilder->buildChoices(),
                'choice_label' => $choicesBuilder->buildChoiceLabelCallback(),
                'choice_value' => $choicesBuilder->buildChoiceValueCallback(),
            ];
        }

        $defaultOptions = [
            'inherit_data' => false,
            'label'        => false,
        ];

        return \array_merge(
            $defaultOptions,
            $choicesOptions ?? [],
            $formTypeOptionsEvent->options,
        );
    }

    /**
     * @throws FilterException If the form does not contain the filter field.
     */
    public function hydrateForm(FormInterface $form, ListSpecification $listSpecification): void
    {
        if ($form->isSubmitted()) {
            return;
        }

        foreach ($listSpecification->getFilters()->getIterator() as $filterDefinition)
        {
            if (!$filterElement = $this->filterElementRegistry->get($filterDefinition->getType())?->getService()) {
                continue;
            }

            if (!$filterElement instanceof HydrateFormContract) {
                continue;
            }

            if ($filterDefinition->isIntrinsic()) {
                continue;
            }

            if (!$filterName = $filterDefinition->getFilterFormFieldName()) {
                throw new FilterException(message: 'Non-intrinsic filter must provide a form field name.');
            }

            if (!$form->has($filterName)) {
                continue;
            }

            try
            {
                $field = $form->get($filterName);
            }
            catch (OutOfBoundsException $exception)
            {
                $filerModel = $filterDefinition->getSourceFilterModel();

                throw new FilterException(
                    message: 'Filter form does not contain field: ' . $filterName,
                    previous: $exception,
                    method: __METHOD__,
                    source: $filerModel ? \sprintf('tl_flare_filter.id=%s', $filerModel->id) : 'filter inlined'
                );
            }

            $filterElement->hydrateForm($field, $listSpecification, $filterDefinition);
        }
    }

    /**
     * @throws FilterException If the form does not contain the filter field.
     */
    public function hydrateFilterElements(FormInterface $form, ListSpecification $listSpecification): void
    {
        if ($form->isSubmitted() && !$form->isValid()) {
            return;
        }

        foreach ($listSpecification->getFilters()->getIterator() as $filter)
        {
            if (!$filterElement = $this->filterElementRegistry->get($filter->getType())?->getService()) {
                continue;
            }

            if ($filter->isIntrinsic()) {
                continue;
            }

            if (!$filterName = $filter->getFilterFormFieldName()) {
                throw new FilterException(message: 'Non-intrinsic filter must provide a form field name.');
            }

            if (!$form->has($filterName)) {
                continue;
            }

            try
            {
                $field = $form->get($filterName);
            }
            catch (OutOfBoundsException $exception)
            {
                $filerModel = $filter->getSourceFilterModel();

                throw new FilterException(
                    message: 'Filter form does not contain field: ' . $filterName,
                    previous: $exception,
                    method: __METHOD__,
                    source: $filerModel ? \sprintf('tl_flare_filter.id=%s', $filerModel->id) : 'filter inlined'
                );
            }

            // $filter->setFormField($field);
        }

        // todo(@ericges): This should not be required: When the filter form is available, each field can be
        //             retrieved from the form itself by its name. Consider a helper method in AbstractFilterElement.
    }
}