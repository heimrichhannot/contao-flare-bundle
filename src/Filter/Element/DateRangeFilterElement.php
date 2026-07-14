<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Element;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\Type\DateRangeFilterType;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsFilterElement(type: self::TYPE)]
class DateRangeFilterElement extends AbstractFilterElement
{
    public const TYPE = 'flare_dateRange';

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('intrinsic')->default(false)->allowedTypes('bool');
        $resolver->define('field')->default(null)->allowedTypes('string', 'null');
    }

    protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void
    {
        $config
            ->set('intrinsic', (bool) $model->intrinsic)
            ->set('field', $model->fieldGeneric ?: null);
    }

    public function buildForm(FormBuilderInterface $builder, FilterContext $context): void
    {
        if ($context->config['intrinsic']) {
            return;
        }

        $builder->add('from', DateType::class, [
            'widget' => 'single_text',
            'label' => 'label.date_range.from',
            'html5' => true,
            'required' => false,
        ]);

        $builder->add('to', DateType::class, [
            'widget' => 'single_text',
            'label' => 'label.date_range.to',
            'html5' => true,
            'required' => false,
        ]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, $this->validateRange(...));
    }

    /**
     * @throws FilterException
     */
    public function buildFilter(FilterBuilderInterface $builder, FilterContext $context, array $data): void
    {
        if (!$field = $context->config['field']) {
            throw new FilterException('Set fieldGeneric in filter model.');
        }

        $builder->add(DateRangeFilterType::class, [
            'field' => $field,
            'from' => $data['from'] ?? null,
            'to' => $data['to'] ?? null,
        ]);
    }

    public function buildDca(DcaBuilder $dca, DcaContext $context): void
    {
        $dca->palette('fieldGeneric');
    }

    /**
     * Ensures `from` <= `to`, replicating the former compound form type's callback constraint.
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
