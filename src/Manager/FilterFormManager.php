<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Manager;

use HeimrichHannot\FlareBundle\Contract\FilterElement\FormTypeOptionsContract;
use HeimrichHannot\FlareBundle\Contract\FilterElement\HydrateFormContract;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterContextCollection;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilderFactory;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

readonly class FilterFormManager
{
    public function __construct(
        private ChoicesBuilderFactory $choicesBuilderFactory,
        private FormFactoryInterface  $formFactory,
    ) {}

    /**
     * @throws FilterException If the form could not be built
     */
    public function buildForm(FilterContextCollection $filters, string $name, ?string $action = null): FormInterface
    {
        $formOptions = [
            'method'             => 'GET',
            'csrf_protection'    => false,
            'translation_domain' => 'flare_form',
            'attr' => [
                'data-flare-keep-query' => 'true',
            ],
        ];

        if ($action) {
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

            if (!$key = \trim((string) $filter->getFilterModel()->formAlias)) {
                $key = (string) $filter->getFilterModel()->id;
            }

            $builder->add($key, $formType, $options);
        }

        // *Always add submit button in template, not to the form builder!*
        // if ($builder->count())
        // {
        //     $builder->add('submit', SubmitType::class, [
        //         'label' => 'submit',
        //     ]);
        // }

        return $builder->getForm();
    }

    public function hydrateForm(FilterContextCollection $filters, FormInterface $form): void
    {
        if ($form->isSubmitted()) {
            return;
        }

        foreach ($filters->getIterator() as $filter)
        {
            $filterElement = $filter->getDescriptor()->getService();
            $filterModel = $filter->getFilterModel();

            if (!$filterModel || !$filterElement instanceof HydrateFormContract)
            {
                continue;
            }

            if (!$filterModel->published || !$filterModel->type || $filterModel->intrinsic)
            {
                continue;
            }

            if (!$form->has((string) $filterModel->id))
            {
                continue;
            }

            $field = $form->get((string) $filterModel->id);

            $filterElement->hydrateForm($filter, $field);
        }
    }

    public function hydrateFilterElements(FilterContextCollection $filters, FormInterface $form): void
    {
        if ($form->isSubmitted() && !$form->isValid()) {
            return;
        }

        foreach ($filters->getIterator() as $filter)
        {
            $filterElement = $filter->getDescriptor()->getService();
            $filterModel = $filter->getFilterModel();

            if (!$filterElement || !$filterModel)
            {
                continue;
            }

            if (!$filterModel->published || !$filterModel->type || $filterModel->intrinsic)
            {
                continue;
            }

            if (!$form->has((string) $filterModel->id))
            {
                continue;
            }

            $field = $form->get((string) $filterModel->id);

            $data = $field->getData();

            if (isset($data))
            {
                $filter->setSubmittedData($data);
            }
        }
    }
}