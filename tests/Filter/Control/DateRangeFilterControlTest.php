<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\Control;

use HeimrichHannot\FlareBundle\Contract\FilterElement\DateBoundsContract;
use HeimrichHannot\FlareBundle\Filter\Control\DateRangeFilterControl;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilder;
use HeimrichHannot\FlareBundle\Filter\FilterScope;
use HeimrichHannot\FlareBundle\Filter\Value\DateRangeValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Forms;
use Symfony\Contracts\Translation\TranslatorInterface;

final class DateRangeFilterControlTest extends TestCase
{
    use BuildsControlScopesTrait;

    private function control(): DateRangeFilterControl
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new DateRangeFilterControl($translator);
    }

    public function testBoundsAppliedOnlyWithDateBoundsContract(): void
    {
        $ctl = $this->control();

        $plain = $this->newFormBuilder();
        $ctl->buildForm($plain, $this->scope($ctl));
        self::assertSame([], $plain->get('from')->getOption('attr'));

        $bounded = new class implements FilterElementInterface, DateBoundsContract {
            public function buildContext(FilterContextBuilder $builder, ?ValueInterface $value): void {}

            public function getDateBounds(FilterScope $scope): array
            {
                return [new \DateTimeImmutable('2024-01-01'), new \DateTimeImmutable('2025-01-01 00:00:00')];
            }
        };

        $builder = $this->newFormBuilder();
        $ctl->buildForm($builder, $this->scope($ctl, [], $bounded));

        foreach (['from', 'to'] as $name) {
            self::assertSame(
                ['min' => '2024-01-01', 'max' => '2024-12-31'],
                $builder->get($name)->getOption('attr'),
            );
        }
    }

    public function testOpenBoundIsOmitted(): void
    {
        $ctl = $this->control();
        $element = new class implements FilterElementInterface, DateBoundsContract {
            public function buildContext(FilterContextBuilder $builder, ?ValueInterface $value): void {}

            public function getDateBounds(FilterScope $scope): array
            {
                return [new \DateTimeImmutable('2024-01-01'), null];
            }
        };

        $builder = $this->newFormBuilder();
        $ctl->buildForm($builder, $this->scope($ctl, [], $element));

        self::assertSame(['min' => '2024-01-01'], $builder->get('from')->getOption('attr'));
    }

    public function testDecodeAndHydrateRoundTrip(): void
    {
        $ctl = $this->control();
        $scope = $this->scope($ctl);

        $value = $ctl->decode(
            ['from' => new \DateTimeImmutable('2024-03-01'), 'to' => null],
            $scope,
        );

        self::assertInstanceOf(DateRangeValue::class, $value);
        self::assertSame('2024-03-01', $value->from->format('Y-m-d'));
        self::assertNull($value->to);

        $data = $ctl->hydrate($value, $scope);
        self::assertSame($value->from, $data['from']);
        self::assertNull($data['to']);
    }

    public function testDecodeEmptyIsNull(): void
    {
        $ctl = $this->control();
        $scope = $this->scope($ctl);

        self::assertNull($ctl->decode(['from' => null, 'to' => null], $scope));
        self::assertNull($ctl->decode(null, $scope));
        self::assertSame(['from' => null, 'to' => null], $ctl->hydrate(null, $scope));
    }

    public function testFromAfterToFailsValidation(): void
    {
        $ctl = $this->control();
        $builder = $this->newFormBuilder();
        $ctl->buildForm($builder, $this->scope($ctl));

        $listeners = $builder->getDeferredListeners();
        self::assertSame(FormEvents::POST_SUBMIT, $listeners[0][0]);

        $factory = Forms::createFormFactory();
        $form = $factory->createBuilder(FormType::class)
            ->add($builder->get('from'))
            ->add($builder->get('to'))
            ->addEventListener(FormEvents::POST_SUBMIT, $listeners[0][1])
            ->getForm();

        $form->submit(['from' => '2024-05-02', 'to' => '2024-05-01']);

        self::assertCount(1, $form->get('from')->getErrors());
    }

    public function testValidRangePasses(): void
    {
        $ctl = $this->control();
        $builder = $this->newFormBuilder();
        $ctl->buildForm($builder, $this->scope($ctl));

        $form = Forms::createFormFactory()->createBuilder(FormType::class)
            ->add($builder->get('from'))
            ->add($builder->get('to'))
            ->addEventListener(FormEvents::POST_SUBMIT, $builder->getDeferredListeners()[0][1])
            ->getForm();

        $form->submit(['from' => '2024-05-01', 'to' => '2024-05-02']);

        self::assertCount(0, $form->get('from')->getErrors());
    }
}
