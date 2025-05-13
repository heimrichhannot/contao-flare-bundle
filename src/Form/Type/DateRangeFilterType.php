<?php

namespace HeimrichHannot\FlareBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class DateRangeFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('from', DateType::class, [
            'widget'    => 'single_text',
            'label'     => 'label.date_range.from',
            'html5'     => true,
            'attr'      => \array_filter([
                'min' => $options['from_min']?->format('Y-m-d'),
                'max' => $options['from_max']?->format('Y-m-d'),
            ]),
        ]);

        $builder->add('to', DateType::class, [
            'widget'    => 'single_text',
            'label'     => 'label.date_range.to',
            'html5'     => true,
            'attr'      => \array_filter([
                'min' => $options['to_min']?->format('Y-m-d'),
                'max' => $options['to_max']?->format('Y-m-d'),
            ]),
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            // custom options
            'from_min'   => null,
            'from_max'   => null,
            'to_min'     => null,
            'to_max'     => null,
            // validation
            'constraints'=> [
                new Callback($this->validateRange(...)),
            ],
        ]);

        // Typen prüfen
        $resolver->setAllowedTypes('from_min', ['null', \DateTimeInterface::class]);
        $resolver->setAllowedTypes('from_max', ['null', \DateTimeInterface::class]);
        $resolver->setAllowedTypes('to_min',   ['null', \DateTimeInterface::class]);
        $resolver->setAllowedTypes('to_max',   ['null', \DateTimeInterface::class]);
    }

    /**
     * Validiert, dass from ≤ to
     *
     * @param array{
     *     from?: \DateTimeInterface,
     *     to?: \DateTimeInterface
     * } $data
     */
    public function validateRange(array $data, ExecutionContextInterface $context): void
    {
        $from = $data['from'] ?? null;
        $to = $data['to'] ?? null;

        if (!empty($from) && !$from instanceof \DateTimeInterface)
        {
            $context->buildViolation('flare.form.date_range.from_invalid')
                ->atPath('from')
                ->addViolation();
        }

        if (!empty($to) && !$to instanceof \DateTimeInterface)
        {
            $context->buildViolation('flare.form.date_range.to_invalid')
                ->atPath('to')
                ->addViolation();
        }

        if (!empty($from) && !empty($to) && $from > $to)
        {
            $context
                ->buildViolation('flare.form.date_range.to_greater_than_from')
                ->atPath('from')
                ->addViolation();
        }
    }
}