<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Control;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\TransformerResolver;
use HeimrichHannot\FlareBundle\Contract\FilterElement\DateBoundsContract;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilderInterface;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterControl;
use HeimrichHannot\FlareBundle\Filter\CallbackFilterModelTransformer;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterScope;
use HeimrichHannot\FlareBundle\Filter\Value\DateRangeValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A from/to pair of date inputs. An element implementing {@see DateBoundsContract} restricts them.
 */
#[AsFilterControl(type: self::TYPE)]
class DateRangeFilterControl extends AbstractFilterControl
{
    public const TYPE = 'flare_date_range';

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('label')->default(null)->allowedTypes('string', 'null');
    }

    public function configureTransformers(TransformerResolver $resolver): void
    {
        $resolver->for(
            sourceClass: FilterModel::class,
            transformer: new CallbackFilterModelTransformer(
                static fn (ConfigBuilder $config, FilterModel $model): ConfigBuilder
                    => $config->set('label', $model->ctl_label ?: null),
            ),
        );
    }

    public function buildDca(DcaBuilderInterface $builder, DcaContext $context): void
    {
        $builder->palette('{control_legend},ctl_label');
    }

    public function buildForm(FilterFormBuilderInterface $builder, FilterScope $scope): void
    {
        $attr = [];
        $element = $scope->filter->element;

        if ($element instanceof DateBoundsContract)
        {
            [$min, $max] = $element->getDateBounds($scope);

            $attr = \array_filter([
                'min' => $min?->format('Y-m-d'),
                'max' => $max === null
                    ? null
                    : \DateTime::createFromInterface($max)->modify('-1 second')->format('Y-m-d'),
            ]);
        }

        foreach (['from', 'to'] as $name)
        {
            $builder->add($name, DateType::class, [
                'widget' => 'single_text',
                'label' => 'label.date_range.' . $name,
                'html5' => true,
                'required' => false,
                'attr' => $attr,
            ]);
        }

        if ($label = $scope->filter->controlConfig['label'] ?? null) {
            $builder->compound(['label' => $label]);
        }

        $builder->addEventListener(FormEvents::POST_SUBMIT, $this->validateRange(...));
    }

    public function decode(mixed $data, FilterScope $scope): ?DateRangeValue
    {
        if (!\is_array($data)) {
            return null;
        }

        return DateRangeValue::tryFrom($data['from'] ?? null, $data['to'] ?? null);
    }

    public function hydrate(?ValueInterface $value, FilterScope $scope): mixed
    {
        $value = $this->expectValue($value, DateRangeValue::class);

        return ['from' => $value?->from, 'to' => $value?->to];
    }

    /**
     * Ensures `from` <= `to`.
     */
    private function validateRange(FormEvent $event): void
    {
        $form = $event->getForm();

        $from = $form->has('from') ? $form->get('from')->getData() : null;
        $to = $form->has('to') ? $form->get('to')->getData() : null;

        if ($from instanceof \DateTimeInterface && $to instanceof \DateTimeInterface && $from > $to) {
            $form->get('from')->addError(new FormError(
                $this->translator->trans('flare.form.date_range.to_greater_than_from', [], 'validators'),
            ));
        }
    }
}
