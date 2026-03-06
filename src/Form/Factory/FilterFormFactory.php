<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Form\Factory;

use Contao\PageModel;
use HeimrichHannot\FlareBundle\Contract\FilterElement\FormTypeOptionsContract;
use HeimrichHannot\FlareBundle\Engine\Context\Interface\FormContextInterface;
use HeimrichHannot\FlareBundle\Event\FilterElementFormTypeOptionsEvent;
use HeimrichHannot\FlareBundle\Event\FilterFormBuildEvent;
use HeimrichHannot\FlareBundle\Event\FilterFormChildOptionsEvent;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Specification\FilterDefinition;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class FilterFormFactory
{
    public function __construct(
        private ChoicesBuilderFactory    $choicesBuilderFactory,
        private EventDispatcherInterface $eventDispatcher,
        private FilterElementRegistry    $filterElementRegistry,
        private FormFactoryInterface     $formFactory,
    ) {}

    /**
     * @throws FlareException If the form could not be built
     */
    public function create(ListSpecification $list, FormContextInterface $context): FormInterface
    {
        $name = $context->getFormName();
        $filters = $list->getFilters();

        $formOptions = [
            'method'             => 'GET',
            'csrf_protection'    => false,
            'translation_domain' => 'flare_form',
            'attr' => [
                'data-flare-keep-query' => 'true',
            ],
        ];

        if ($action = $this->resolveFormAction($context)) {
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

            $options = $this->resolveFieldOptions($list, $filterDefinition);

            $childName = $filterDefinition->getAlias();

            /** @var FilterFormChildOptionsEvent $childOptionsEvent */
            $childOptionsEvent = $this->eventDispatcher->dispatch(new FilterFormChildOptionsEvent(
                listSpecification: $list,
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

    /**
     * @throws FlareException If form type options could not be retrieved from the filter element.
     */
    private function resolveFieldOptions(
        ListSpecification $list,
        FilterDefinition  $filter,
    ): array {
        $choicesBuilder = $this->choicesBuilderFactory->createChoicesBuilder();

        $formTypeOptionsEvent = new FilterElementFormTypeOptionsEvent(
            choicesBuilder: $choicesBuilder,
            list: $list,
            filter: $filter,
            options: [],
        );

        $filterElement = $this->filterElementRegistry->get($filter->getType())?->getService();
        if ($filterElement instanceof FormTypeOptionsContract)
        {
            $filterElement->handleFormTypeOptions($formTypeOptionsEvent);
        }

        /** @var FilterElementFormTypeOptionsEvent $formTypeOptionsEvent */
        $formTypeOptionsEvent = $this->eventDispatcher->dispatch($formTypeOptionsEvent);

        $choicesBuilder = $formTypeOptionsEvent->choicesBuilder;
        if ($choicesBuilder->isEnabled())
        {
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