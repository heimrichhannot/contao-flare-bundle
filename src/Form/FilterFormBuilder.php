<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Form;

use HeimrichHannot\FlareBundle\Contract\FilterElement\FormTypeOptionsContract;
use HeimrichHannot\FlareBundle\Event\FilterElementFormTypeOptionsEvent;
use HeimrichHannot\FlareBundle\Event\FilterFormChildOptionsEvent;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\FilterElement\FilterElementContext;
use HeimrichHannot\FlareBundle\Form\Factory\ChoicesBuilderFactory;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class FilterFormBuilder implements FilterFormBuilderInterface
{
    public function __construct(
        private FormBuilderInterface     $rootBuilder,
        private ChoicesBuilderFactory    $choicesBuilderFactory,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * @throws FlareException
     */
    public function add(FilterElementContext $context, ?string $formType = null, array $options = []): static
    {
        $filter = $context->filter;
        $formType ??= $context->descriptor->getFormType();

        if (!$formType) {
            return $this;
        }

        $childName = $filter->getAlias();
        if (!$childName) {
            throw new FlareException(message: 'Non-intrinsic filter must provide a form field name.');
        }

        $choicesBuilder = $this->choicesBuilderFactory->createChoicesBuilder();

        $formTypeOptionsEvent = new FilterElementFormTypeOptionsEvent(
            choicesBuilder: $choicesBuilder,
            list: $context->list,
            filter: $filter,
            options: $options,
        );

        $element = $context->descriptor->getService();
        if ($element instanceof FormTypeOptionsContract) {
            $element->handleFormTypeOptions($formTypeOptionsEvent);
        }

        /** @var FilterElementFormTypeOptionsEvent $formTypeOptionsEvent */
        $formTypeOptionsEvent = $this->eventDispatcher->dispatch($formTypeOptionsEvent);

        $choicesBuilder = $formTypeOptionsEvent->choicesBuilder;
        if ($choicesBuilder->isEnabled()) {
            $choicesOptions = [
                'choices' => $choicesBuilder->buildChoices(),
                'choice_label' => $choicesBuilder->buildChoiceLabelCallback(),
                'choice_value' => $choicesBuilder->buildChoiceValueCallback(),
            ];
        }

        $resolvedOptions = \array_merge(
            [
                'inherit_data' => false,
                'label' => false,
            ],
            $choicesOptions ?? [],
            $formTypeOptionsEvent->options,
        );

        /** @var FilterFormChildOptionsEvent $childOptionsEvent */
        $childOptionsEvent = $this->eventDispatcher->dispatch(new FilterFormChildOptionsEvent(
            listSpecification: $context->list,
            configuredFilter: $filter,
            parentFormName: $this->rootBuilder->getName(),
            formName: $childName,
            options: $resolvedOptions,
        ));

        $this->rootBuilder->add($childName, $formType, $childOptionsEvent->options);

        return $this;
    }

    public function getRootBuilder(): FormBuilderInterface
    {
        return $this->rootBuilder;
    }
}
