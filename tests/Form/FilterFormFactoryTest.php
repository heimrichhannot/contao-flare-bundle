<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Form;

use HeimrichHannot\FlareBundle\Config\SchemaResolver;
use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\Context\Interface\FormContextInterface;
use HeimrichHannot\FlareBundle\Event\FilterElementFormBuiltEvent;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterContextFactory;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterFormFactory;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterOptionsResolver;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Security\Csrf\CsrfTokenManager;

final class FilterFormFactoryTest extends TestCase
{
    private EventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        $this->eventDispatcher = new EventDispatcher();
    }

    private function createFactory(): FilterFormFactory
    {
        // The CSRF extension only needs to define the "csrf_protection" option; the factory
        // always disables it, so the token manager is never used.
        $formFactory = Forms::createFormFactoryBuilder()
            ->addExtension(new CsrfExtension(new CsrfTokenManager()))
            ->getFormFactory();

        return new FilterFormFactory(
            eventDispatcher: $this->eventDispatcher,
            filterContextFactory: new FilterContextFactory(new FilterOptionsResolver(new SchemaResolver())),
            formFactory: $formFactory,
        );
    }

    private function createForm(array $filters): FormInterface
    {
        $driver = new class implements ListDriverInterface {
            public function resolveDcTable(string $type, array $config, array $attributes): string
            {
                return (string) ($config['dc'] ?? '');
            }
        };

        $list = new ListSpec(
            driver: $driver,
            type: 'test_list',
            dc: 'tl_test',
            filters: $filters,
        );

        $context = new class implements ContextInterface, FormContextInterface {
            public static function getContextType(): string
            {
                return 'test';
            }

            public function getFormName(): string
            {
                return 'flare_test';
            }

            public function createFormActionUrl(): ?string
            {
                return null;
            }
        };

        return $this->createFactory()->create($list, $context);
    }

    /**
     * Creates an element building its form via the given callable.
     *
     * @param callable(FilterFormBuilderInterface, FilterContext): void $buildForm
     */
    private function element(callable $buildForm): FilterElementInterface
    {
        return new class($buildForm) implements FilterElementInterface {
            /** @var callable */
            private $buildForm;

            public function __construct(callable $buildForm)
            {
                $this->buildForm = $buildForm;
            }

            public function buildForm(FilterFormBuilderInterface $builder, FilterContext $context): void
            {
                ($this->buildForm)($builder, $context);
            }

            public function buildFilter(FilterBuilderInterface $builder, FilterContext $context, array $values): void {}
        };
    }

    public function testSingleFieldMountsFlatUnderTheAlias(): void
    {
        $element = $this->element(static function (FilterFormBuilderInterface $builder): void {
            $builder->single(TextType::class, ['required' => false]);
            $builder->setAttribute('custom.attr', 'kept');
            $builder->addEventListener(FormEvents::POST_SUBMIT, static function (): void {});
        });

        $form = $this->createForm(['suche' => new Filter(element: $element, type: 'test_element', alias: 'suche')]);

        $this->assertTrue($form->has('suche'));

        $config = $form->get('suche')->getConfig();

        $this->assertInstanceOf(TextType::class, $config->getType()->getInnerType());
        $this->assertTrue($config->getAttribute(FilterContext::ATTR_SINGLE_FIELD));
        $this->assertSame('kept', $config->getAttribute('custom.attr'));
        $this->assertInstanceOf(FilterContext::class, $config->getAttribute(FilterContext::ATTR_SELF));
        $this->assertTrue(
            $config->getEventDispatcher()->hasListeners(FormEvents::POST_SUBMIT),
            'Deferred listeners must be replayed onto the mounted builder',
        );
    }

    public function testSingleWithCompanionFieldMountsNestedCompound(): void
    {
        $element = $this->element(static function (FilterFormBuilderInterface $builder): void {
            $builder->single(TextType::class, ['required' => false]);
            $builder->add('extra', TextType::class, ['required' => false]);
        });

        $form = $this->createForm(['suche' => new Filter(element: $element, type: 'test_element', alias: 'suche')]);

        $child = $form->get('suche');

        $this->assertInstanceOf(FormType::class, $child->getConfig()->getType()->getInnerType());
        $this->assertNull($child->getConfig()->getAttribute(FilterContext::ATTR_SINGLE_FIELD));
        $this->assertTrue($child->has(FilterContext::SINGLE_VALUE));
        $this->assertTrue($child->has('extra'));
    }

    public function testMultiFieldElementMountsNestedCompound(): void
    {
        $element = $this->element(static function (FilterFormBuilderInterface $builder): void {
            $builder->add('from', TextType::class);
            $builder->add('to', TextType::class);
            $builder->addEventListener(FormEvents::POST_SUBMIT, static function (): void {});
        });

        $form = $this->createForm(['range' => new Filter(element: $element, type: 'test_element', alias: 'range')]);

        $child = $form->get('range');

        $this->assertInstanceOf(FormType::class, $child->getConfig()->getType()->getInnerType());
        $this->assertTrue($child->has('from'));
        $this->assertTrue($child->has('to'));
        $this->assertTrue(
            $child->getConfig()->getEventDispatcher()->hasListeners(FormEvents::POST_SUBMIT),
            'Deferred listeners must be replayed onto the mounted compound',
        );
    }

    public function testElementWithoutFieldsIsNotMounted(): void
    {
        $element = $this->element(static function (): void {});

        $form = $this->createForm(['empty' => new Filter(element: $element, type: 'test_element', alias: 'empty')]);

        $this->assertFalse($form->has('empty'));
    }

    public function testInvalidAliasIsSkipped(): void
    {
        $element = $this->element(static function (FilterFormBuilderInterface $builder): void {
            $builder->single(TextType::class);
        });

        $form = $this->createForm(['x' => new Filter(element: $element, type: 'test_element', alias: '_.tl_flare_filter.1')]);

        $this->assertSame(0, \count($form));
    }

    public function testCancelledEventPreventsMounting(): void
    {
        $this->eventDispatcher->addListener(
            FilterElementFormBuiltEvent::class,
            static fn (FilterElementFormBuiltEvent $event) => $event->cancel(),
        );

        $element = $this->element(static function (FilterFormBuilderInterface $builder): void {
            $builder->single(TextType::class);
        });

        $form = $this->createForm(['suche' => new Filter(element: $element, type: 'test_element', alias: 'suche')]);

        $this->assertFalse($form->has('suche'));
    }
}
