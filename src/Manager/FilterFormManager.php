<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Manager;

use HeimrichHannot\FlareBundle\Contract\FilterElement\FormTypeOptionsContract;
use HeimrichHannot\FlareBundle\Contract\FilterElement\HydrateFormContract;
use HeimrichHannot\FlareBundle\Event\FilterFormBuildEvent;
use HeimrichHannot\FlareBundle\Event\FilterFormChildOptionsEvent;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterContextCollection;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilderFactory;
use HeimrichHannot\FlareBundle\List\ListDefinition;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use Symfony\Component\Form\Exception\OutOfBoundsException;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class FilterFormManager
{
    public function __construct(
        private ChoicesBuilderFactory    $choicesBuilderFactory,
        private EventDispatcherInterface $eventDispatcher,
        private FilterElementRegistry    $filterElementRegistry,
        private FormFactoryInterface     $formFactory,
    ) {}

    /**
     * @throws FilterException If the form could not be built
     */
    public function buildForm(ListDefinition $listDefinition): FormInterface
    {
        $name = $listDefinition->getFilterFormName();
        $filters = $listDefinition->getFilters();

        $formOptions = [
            'method'             => 'GET',
            'csrf_protection'    => false,
            'translation_domain' => 'flare_form',
            'attr' => [
                'data-flare-keep-query' => 'true',
            ],
        ];

        if ($action = $listDefinition->getFormAction()) {
            $formOptions['action'] = $action;
        }

        $builder = $this->formFactory->createNamedBuilder($name, FormType::class, null, $formOptions);

        $defaultOptions = [
            'inherit_data' => false,
            'label'        => false,
        ];

        foreach ($filters->getIterator() as $filter)
            // Apply only non-intrinsic, published filters with a valid type
        {
            $formType = $filter->getDescriptor()->getFormType();
            $filterElement = $filter->getDescriptor()->getService();
            $filterModel = $filter->getFilterModel();

            if (!$formType || !$filterElement || !$filterModel)
            {
                continue;
            }

            if (!$filterModel->published || !$filterModel->type || $filterModel->intrinsic)
            {
                continue;
            }

            $options = $defaultOptions;

            if ($filterElement instanceof FormTypeOptionsContract)
            {
                try
                {
                    $choicesBuilder = $this->choicesBuilderFactory->createChoicesBuilder();
                    $generatedOptions = $filterElement->getFormTypeOptions($filter, $choicesBuilder);

                    $choicesOptions = $choicesBuilder->isEnabled() ? [
                        'choices' => $choicesBuilder->buildChoices(),
                        'choice_label' => $choicesBuilder->buildChoiceLabelCallback(),
                        'choice_value' => $choicesBuilder->buildChoiceValueCallback(),
                    ] : [];

                    $options = \array_merge($defaultOptions, $choicesOptions, $generatedOptions);
                }
                catch (FilterException $e)
                {
                    $method = $e->getMethod() ?? ($filterElement::class . '::getFormTypeOptions');

                    throw new FilterException(
                        \sprintf('[FLARE] Form denied: %s', $e->getMessage()),
                        code: $e->getCode(), previous: $e, method: $method,
                        source: \sprintf('tl_flare_filter.id=%s', $filterModel->id)
                    );
                }
            }

            $childName = $filter->getFilterModel()->getFormName();

            /** @var FilterFormChildOptionsEvent $event */
            $event = $this->eventDispatcher->dispatch(new FilterFormChildOptionsEvent(
                filterContext: $filter,
                filterContextCollection: $filters,
                parentFormName: $name,
                formName: $childName,
                options: $options,
            ));

            $options = $event->options;

            $builder->add($childName, $formType, $options);
        }

        // *Always add submit button in template, not to the form builder!*
        // if ($builder->count())
        // {
        //     $builder->add('submit', SubmitType::class, [
        //         'label' => 'submit',
        //     ]);
        // }

        $event = $this->eventDispatcher->dispatch(new FilterFormBuildEvent(
            filters: $filters,
            formName: $name,
            formBuilder: $builder,
        ));

        $builder = $event->formBuilder;

        return $builder->getForm();
    }

    /**
     * @throws FilterException If the form does not contain the filter field.
     */
    public function hydrateForm(FormInterface $form, ListDefinition $listDefinition): void
    {
        if ($form->isSubmitted()) {
            return;
        }

        foreach ($listDefinition->getFilters()->getIterator() as $filterDefinition)
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

            $filterElement->hydrateForm($listDefinition, $filterDefinition, $field);
        }
    }

    /**
     * @throws FilterException If the form does not contain the filter field.
     */
    public function hydrateFilterElements(FormInterface $form, ListDefinition $listDefinition): void
    {
        if ($form->isSubmitted() && !$form->isValid()) {
            return;
        }

        foreach ($listDefinition->getFilters()->getIterator() as $filter)
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