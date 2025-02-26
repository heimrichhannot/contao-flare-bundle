<?php

namespace HeimrichHannot\FlareBundle\Builder;

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;

readonly class FilterFormBuilder
{
    public function __construct(
        private FormFactoryInterface $formFactory
    ) {}

    /**
     * @param class-string<FormTypeInterface>[] $formTypes
     * @return FormInterface
     */
    public function build(array $formTypes): FormInterface
    {
        $builder = $this->formFactory->createBuilder(FormType::class, null, [
            'csrf_protection' => false,
        ]);

        foreach ($formTypes as $key => $formType) {
            $builder->add($key, $formType,[
                'inherit_data' => true,
                'label'        => false,
            ]);
        }

        return $builder->getForm();
    }
}