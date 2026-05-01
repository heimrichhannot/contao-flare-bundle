<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Form\Factory;

use Contao\PageModel;
use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\Context\Interface\FormContextInterface;
use HeimrichHannot\FlareBundle\Event\FilterFormBuildEvent;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\FilterElement\FilterElementContext;
use HeimrichHannot\FlareBundle\FilterElement\FilterElementInterface;
use HeimrichHannot\FlareBundle\Form\FilterFormBuilder;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
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
        if (!$context instanceof ContextInterface) {
            throw new FlareException('Filter form context must implement ContextInterface.', method: __METHOD__);
        }

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
        $filterFormBuilder = new FilterFormBuilder(
            rootBuilder: $builder,
            choicesBuilderFactory: $this->choicesBuilderFactory,
            eventDispatcher: $this->eventDispatcher,
        );

        foreach ($filters->getIterator() as $configuredFilter)
        {
            if (!$configuredFilter->getElementType()) {
                continue;
            }

            if (!$descriptor = $this->filterElementRegistry->get($configuredFilter->getElementType())) {
                continue;
            }

            $element = $descriptor->getService();

            if ($element instanceof FilterElementInterface) {
                $element->buildForm($filterFormBuilder, new FilterElementContext(
                    list: $list,
                    filter: $configuredFilter,
                    engineContext: $context,
                    descriptor: $descriptor,
                ));
            }
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
