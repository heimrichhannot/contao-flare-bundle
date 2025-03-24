<?php

namespace HeimrichHannot\FlareBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LicenseFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('license', ChoiceType::class, [
            'label' => 'License',
            'choices' => [
                'MIT' => 'mit',
                'GNU GPL' => 'gpl',
                'Apache 2.0' => 'apache',
                'BSD' => 'bsd',
            ],
            'placeholder' => 'Select a license',
            'required' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}