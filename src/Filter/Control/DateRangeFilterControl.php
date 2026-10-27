<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Control;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Contract\FilterElement\DateBoundsContract;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilderInterface;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterControl;
use HeimrichHannot\FlareBundle\Filter\ControlScope;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
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

    protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void
    {
        $config->set('label', $model->ctl_label ?: null);
    }

    public function buildDca(DcaBuilderInterface $builder, DcaContext $context): void
    {
        $builder->palette('{control_legend},ctl_label');
    }

    public function buildForm(FilterFormBuilderInterface $builder, ControlScope $scope): void
    {
        $attr = [];
        $element = $scope->filter->element->component;

        if ($element instanceof DateBoundsContract)
        {
            $bounds = $element->getDateBounds($scope->forElement());

            $attr = \array_filter([
                'min' => $bounds->min?->format('Y-m-d'),
                'max' => $bounds->max?->modify('-1 second')->format('Y-m-d'),
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

        if ($label = $scope->filter->control->config->all()['label'] ?? null) {
            $builder->compound(['label' => $label]);
        }

        $builder->addEventListener(FormEvents::POST_SUBMIT, $this->validateRange(...));
    }

    /**
     * @param array{from?: ?\DateTimeInterface, to?: ?\DateTimeInterface}|null $data The data of the compound
     *   form, keyed by its children.
     */
    public function decode(ControlScope $scope, mixed $data): ?DateRangeValue
    {
        if (!\is_array($data)) {
            return null;
        }

        return DateRangeValue::tryFrom($data['from'] ?? null, $data['to'] ?? null);
    }

    /**
     * @return array{from: ?\DateTimeImmutable, to: ?\DateTimeImmutable}
     */
    public function hydrate(ControlScope $scope, ?ValueInterface $value): array
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
