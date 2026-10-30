<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Form;

use HeimrichHannot\FlareBundle\Form\FilterFormBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;

final class FilterFormBuilderTest extends TestCase
{
    private function createBuilder(string $name = 'test'): FilterFormBuilder
    {
        return new FilterFormBuilder($name, null, new EventDispatcher(), Forms::createFormFactory());
    }

    public function testSingleIsNullByDefault(): void
    {
        $this->assertNull($this->createBuilder()->getSingle());
    }

    public function testSingleRecordsTypeAndOptions(): void
    {
        $builder = $this->createBuilder();

        $result = $builder->single(TextType::class, ['required' => false]);

        $this->assertSame($builder, $result);
        $this->assertSame(
            ['type' => TextType::class, 'options' => ['required' => false]],
            $builder->getSingle(),
        );
        $this->assertSame(0, $builder->count(), 'single() must not add a child');
    }

    public function testSingleOverwritesPreviousDeclaration(): void
    {
        $builder = $this->createBuilder();

        $builder->single(TextType::class, ['required' => true]);
        $builder->single(TextType::class, ['required' => false]);

        $this->assertSame(
            ['type' => TextType::class, 'options' => ['required' => false]],
            $builder->getSingle(),
        );
    }

    public function testAddEventListenerDefersInsteadOfRegistering(): void
    {
        $builder = $this->createBuilder();
        $first = static function (): void {};
        $second = static function (): void {};

        $result = $builder
            ->addEventListener(FormEvents::POST_SUBMIT, $first)
            ->addEventListener(FormEvents::PRE_SET_DATA, $second, 7);

        $this->assertSame($builder, $result);
        $this->assertSame(
            [
                [FormEvents::POST_SUBMIT, $first, 0],
                [FormEvents::PRE_SET_DATA, $second, 7],
            ],
            $builder->getDeferredListeners(),
        );
        $this->assertFalse(
            $builder->getEventDispatcher()->hasListeners(FormEvents::POST_SUBMIT),
            'Deferred listeners must not reach the collector\'s own dispatcher',
        );
    }

    public function testAddEventSubscriberThrows(): void
    {
        $subscriber = new class implements EventSubscriberInterface {
            public static function getSubscribedEvents(): array
            {
                return [];
            }
        };

        $this->expectException(\LogicException::class);

        $this->createBuilder()->addEventSubscriber($subscriber);
    }

    public function testGetFormThrows(): void
    {
        $this->expectException(\LogicException::class);

        $this->createBuilder()->getForm();
    }

    public function testAddProducesRealMountableChildBuilders(): void
    {
        $builder = $this->createBuilder();

        $builder->add('field', TextType::class, ['required' => false]);

        $this->assertSame(1, $builder->count());

        $child = $builder->get('field');

        $this->assertInstanceOf(FormBuilderInterface::class, $child);
        $this->assertNotInstanceOf(FilterFormBuilder::class, $child);
        $this->assertInstanceOf(FormInterface::class, $child->getForm());
    }
}
