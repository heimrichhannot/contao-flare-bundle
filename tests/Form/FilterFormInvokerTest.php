<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Form;

use HeimrichHannot\FlareBundle\Engine\Context\ConfigurationContext;
use HeimrichHannot\FlareBundle\Event\FilterControlBuildFormEvent;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Control\FilterControlInterface;
use HeimrichHannot\FlareBundle\Filter\ControlScope;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilder;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Form\FilterFormInvoker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Forms;

final class FilterFormInvokerTest extends TestCase
{
    use BuildsFormFixturesTrait;

    private EventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        $this->eventDispatcher = new EventDispatcher();
    }

    private function build(FilterControlInterface $control, string $alias = 'suche'): ?FilterFormBuilder
    {
        $filter = $this->filter($alias, $control);
        $invoker = new FilterFormInvoker($this->eventDispatcher, Forms::createFormFactory());

        return $invoker->build(new ControlScope($this->listSpec([$filter]), $filter, new ConfigurationContext()));
    }

    private function failing(\Exception $e): FilterControlInterface
    {
        return $this->control(static function () use ($e): void {
            throw $e;
        });
    }

    public function testReturnsTheBuilderOfASingleField(): void
    {
        $builder = $this->build($this->single());

        self::assertSame('suche', $builder?->getName());
        self::assertSame(TextType::class, $builder->getSingle()['type'] ?? null);
        self::assertInstanceOf(ControlScope::class, $builder->getAttribute(ControlScope::ATTR_SELF));
    }

    public function testReturnsNullWithoutFields(): void
    {
        self::assertNull($this->build($this->control(static function (): void {})));
    }

    public function testReturnsNullWhenCancelled(): void
    {
        $this->eventDispatcher->addListener(
            FilterControlBuildFormEvent::class,
            static fn (FilterControlBuildFormEvent $event) => $event->cancel(),
        );

        self::assertNull($this->build($this->single()));
    }

    public function testFilterExceptionFromBuildFormIsRethrownWithItsMethod(): void
    {
        try {
            $this->build($this->failing(new FilterException('no choices')));
            self::fail('No exception thrown.');
        } catch (FilterException $e) {
            self::assertSame('no choices', $e->getMessage());
            self::assertStringEndsWith('::buildForm', (string) $e->getMethod());
        }
    }

    public function testUnexpectedExceptionFromBuildFormIsWrapped(): void
    {
        try {
            $this->build($this->failing(new \RuntimeException('connection lost')));
            self::fail('No exception thrown.');
        } catch (FilterException $e) {
            self::assertInstanceOf(\RuntimeException::class, $e->getPrevious());
        }
    }

    public function testListenerFlareExceptionIsWrapped(): void
    {
        $this->eventDispatcher->addListener(FilterControlBuildFormEvent::class, static function (): void {
            throw new FlareException('The listener cannot build this form.');
        });

        $this->expectException(FilterException::class);
        $this->expectExceptionMessage('The listener cannot build this form.');

        $this->build($this->single());
    }

    public function testListenerOtherExceptionEscapes(): void
    {
        $this->eventDispatcher->addListener(FilterControlBuildFormEvent::class, static function (): void {
            throw new \RuntimeException('A bug in the listener.');
        });

        $this->expectException(\RuntimeException::class);

        $this->build($this->single());
    }

    public function testSingleFieldWithChildrenIsAFilterException(): void
    {
        $control = $this->control(static function (FilterFormBuilderInterface $builder): void {
            $builder->single(TextType::class, ['required' => false]);
            $builder->add('extra', TextType::class, ['required' => false]);
        });

        $this->expectException(FilterException::class);
        $this->expectExceptionMessage('cannot declare a single field and add children at the same time');

        $this->build($control);
    }

    public function testInvalidAliasIsAFilterException(): void
    {
        $this->expectException(FilterException::class);
        $this->expectExceptionMessage('"price.min"');

        $this->build($this->single(), 'price.min');
    }
}
