<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Element;

use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Engine\Context\ValidationContext;
use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\Type\CalendarCurrentFilterType;
use HeimrichHannot\FlareBundle\Util\DateTimeHelper;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsFilterElement(type: self::TYPE)]
class CalendarCurrentFilterElement extends AbstractFilterElement
{
    public const TYPE = 'flare_calendar_current';

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('intrinsic')->default(false)->allowedTypes('bool');
        $resolver->define('is_limited')->default(false)->allowedTypes('bool');
        $resolver->define('configure_start')->default(null)->allowedTypes('string', 'null');
        $resolver->define('configure_stop')->default(null)->allowedTypes('string', 'null');
        $resolver->define('start_at')->default(null)->allowedTypes('string', 'null');
        $resolver->define('stop_at')->default(null)->allowedTypes('string', 'null');
        $resolver->define('has_extended_events')->default(false)->allowedTypes('bool');
    }

    public function configFromRow(array $row): array
    {
        return [
            'intrinsic' => (bool) ($row['intrinsic'] ?? false),
            'is_limited' => (bool) ($row['isLimited'] ?? false),
            'configure_start' => ($row['configureStart'] ?? null) ?: null,
            'configure_stop' => ($row['configureStop'] ?? null) ?: null,
            'start_at' => ($row['startAt'] ?? null) ?: null,
            'stop_at' => ($row['stopAt'] ?? null) ?: null,
            'has_extended_events' => (bool) ($row['hasExtendedEvents'] ?? false),
        ];
    }

    public function buildForm(FormBuilderInterface $builder, FilterContext $context): void
    {
        $config = $context->config;

        if ($config['intrinsic']) {
            return;
        }

        [$min, $max] = $this->resolveFormLimits($config);

        $minAttr = $min?->format('Y-m-d');
        $maxAttr = null;

        if ($max !== null) {
            $maxAttr = \DateTime::createFromInterface($max)->modify('-1 second')->format('Y-m-d');
        }

        $attr = \array_filter([
            'min' => $minAttr,
            'max' => $maxAttr,
        ]);

        $builder->add('from', DateType::class, [
            'widget' => 'single_text',
            'label' => 'label.date_range.from',
            'html5' => true,
            'required' => false,
            'attr' => $attr,
        ]);

        $builder->add('to', DateType::class, [
            'widget' => 'single_text',
            'label' => 'label.date_range.to',
            'html5' => true,
            'required' => false,
            'attr' => $attr,
        ]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, $this->validateRange(...));
    }

    public function buildFilter(FilterBuilderInterface $builder, FilterContext $context, array $data): void
    {
        $config = $context->config;

        if (!$config['is_limited'] && $context->engineContext instanceof ValidationContext) {
            return;
        }

        $value = $this->processRuntimeValue($data) ?? [];
        $from = $value['from'] ?? null;
        $to = $value['to'] ?? null;

        $start = \strtotime((string) $config['start_at']) ?: 0;
        $stop = \strtotime((string) $config['stop_at']) ?: DateTimeHelper::maxTimestamp();

        if ($from instanceof \DateTimeInterface)
        {
            $from = $from->getTimestamp();

            if (!$config['is_limited'] || $from >= $start) {
                $start = $from;
            }
        }

        if ($to instanceof \DateTimeInterface)
        {
            $to = $to->getTimestamp();

            if (!$config['is_limited'] || $to <= $stop) {
                $stop = $to;
            }
        }

        $builder->add(CalendarCurrentFilterType::class, [
            'start' => $start,
            'stop' => $stop,
            'has_extended_events' => $config['has_extended_events'],
        ]);
    }

    public function buildDca(DcaBuilder $dca, DcaContext $context): void
    {
        $palette = '{date_start_legend},configureStart,hasExtendedEvents;{date_stop_legend},configureStop;';

        if (!$context->filterModel?->intrinsic) {
            $palette .= '{form_legend},isLimited;';
        }

        $dca->palette($palette);
    }

    /**
     * Resolves the form's lower and upper date limits from the canonical config, replicating
     * the former handleFormTypeOptions() logic (from_min/to_min and from_max/to_max).
     *
     * @param array<string, mixed> $config
     *
     * @return array{0: ?\DateTime, 1: ?\DateTime}
     */
    private function resolveFormLimits(array $config): array
    {
        if (!$config['is_limited']) {
            return [null, null];
        }

        $min = null;
        $max = null;

        if ($config['configure_start']
            && $config['start_at']
            && ($startAt = \strtotime($config['start_at'])))
        {
            $min = DateTimeHelper::timestampToDateTime($startAt);
        }

        if ($config['configure_stop']
            && $config['stop_at']
            && ($stopAt = \strtotime($config['stop_at'])))
        {
            $max = DateTimeHelper::timestampToDateTime($stopAt);
        }

        return [$min, $max];
    }

    /**
     * @param array<string, mixed> $value
     *
     * @return array{from: ?\DateTimeInterface, to: ?\DateTimeInterface}|null
     */
    private function processRuntimeValue(array $value): ?array
    {
        if (!\array_key_exists('from', $value) && !\array_key_exists('to', $value))
        {
            if (\count($value) !== 2)
            {
                return null;
            }

            $value = \array_values($value);

            return [
                'from' => $this->mixedToDateTime($value[0] ?? null),
                'to' => $this->mixedToDateTime($value[1] ?? null),
            ];
        }

        return [
            'from' => $this->mixedToDateTime($value['from'] ?? null),
            'to' => $this->mixedToDateTime($value['to'] ?? null),
        ];
    }

    private function mixedToDateTime(mixed $input): ?\DateTimeInterface
    {
        if (!$input) {
            return null;
        }

        if ($input instanceof \DateTimeInterface) {
            return $input;
        }

        if (\is_numeric($input)) {
            return \DateTimeImmutable::createFromFormat('U', (string) $input) ?: null;
        }

        if (\is_string($input)) {
            return new \DateTimeImmutable($input);
        }

        return null;
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
