<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Form;

use HeimrichHannot\FlareBundle\Config\ComponentConfig;
use HeimrichHannot\FlareBundle\Engine\Intent\ConfigurationIntent;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\Control\FilterControlInterface;
use HeimrichHannot\FlareBundle\Filter\Control\KeywordsFilterControl;
use HeimrichHannot\FlareBundle\Filter\ControlBinding;
use HeimrichHannot\FlareBundle\Filter\ControlContext;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\ElementBinding;
use HeimrichHannot\FlareBundle\Filter\ElementContext;
use HeimrichHannot\FlareBundle\Filter\FilterCompound;
use HeimrichHannot\FlareBundle\Filter\FilterEffectBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Value\KeywordsValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Form\FilterMount;
use HeimrichHannot\FlareBundle\Form\FormHarness;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\ListCompound;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Forms;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\Translation\TranslatorTrait;

final class FormHarnessTest extends TestCase
{
    /** @var list<array{string, string}> */
    private array $logs = [];

    protected function setUp(): void
    {
        $this->logs = [];
    }

    public function testDecodeSkipsMountsWhoseChildWasRemoved(): void
    {
        $harness = $this->harness(['q' => 'foo'], remove: ['q']);

        self::assertSame([], $harness->decode());
        self::assertNull($harness->getChild('q'));
        self::assertNotNull($harness->getMount('q'));
    }

    public function testDecodeIsKeyedByAliasAndCoversEveryMountWhenSubmitted(): void
    {
        $harness = $this->harness(['a' => 'x', 'b' => 'y']);

        $harness->getForm()->submit(['a' => 'one', 'b' => '']);

        $values = $harness->decode();

        self::assertSame(['a', 'b'], \array_keys($values));
        self::assertSame('one', $values['a']->keywords);
        self::assertNull($values['b']);
    }

    public function testDecodeOmitsNullEntriesWhenUnsubmitted(): void
    {
        $harness = $this->harness(['a' => 'x', 'b' => null]);

        self::assertSame(['a'], \array_keys($harness->decode()));
    }

    public function testDecodeFailureOfASubmittedValueBecomesAFormErrorAndNoValue(): void
    {
        $harness = $this->harness(['a' => null, 'b' => null], control: new RejectingKeywordsControl());

        $harness->getForm()->submit(['a' => 'rejected', 'b' => 'fine']);

        $values = $harness->decode();

        self::assertSame(['a', 'b'], \array_keys($values));
        self::assertNull($values['a']);
        self::assertSame('fine', $values['b']?->keywords);

        $errors = $harness->getForm()->get('a')->getErrors();
        self::assertCount(1, $errors);
        self::assertSame('flare.form.invalid_value', $errors[0]->getMessage());
        self::assertCount(0, $harness->getForm()->get('b')->getErrors());
        self::assertFalse($harness->getForm()->isValid(), 'an invalid submission renders an empty list');

        self::assertCount(1, $this->logs);
        self::assertSame('warning', $this->logs[0][0]);
        self::assertStringContainsString('"a"', $this->logs[0][1]);
    }

    public function testDecodeFailureOfAnInitialValueDropsTheValueWithoutAFormError(): void
    {
        $harness = $this->harness(['a' => 'rejected', 'b' => 'fine'], control: new RejectingKeywordsControl());

        $values = $harness->decode();

        self::assertSame(['b'], \array_keys($values), 'the visitor did not enter the initial value');
        self::assertCount(0, $harness->getForm()->getErrors(true));
        self::assertCount(1, $this->logs);
    }

    public function testUnexpectedExceptionInDecodeIsARejectionLoggedAsError(): void
    {
        $harness = $this->harness(['a' => null], control: new RejectingKeywordsControl());

        $harness->getForm()->submit(['a' => 'crashing']);

        self::assertSame(['a' => null], $harness->decode());
        self::assertCount(1, $harness->getForm()->get('a')->getErrors());
        self::assertSame('error', $this->logs[0][0]);
    }

    public function testUnexpectedExceptionInDecodeIsRethrownWrappedInDebugMode(): void
    {
        $harness = $this->harness(['a' => null], control: new RejectingKeywordsControl(), debug: true);

        $harness->getForm()->submit(['a' => 'crashing']);

        $this->expectException(FilterException::class);

        $harness->decode();
    }

    public function testRejectionIsAFormErrorInDebugModeToo(): void
    {
        $harness = $this->harness(['a' => null], control: new RejectingKeywordsControl(), debug: true);

        $harness->getForm()->submit(['a' => 'rejected']);

        self::assertSame(['a' => null], $harness->decode());
        self::assertSame('warning', $this->logs[0][0]);
    }

    /**
     * @param array<string, ?string> $initial Alias => initial keywords (null: no initial value).
     * @param list<string> $remove Aliases whose child is removed from the form after mounting.
     */
    private function harness(
        array                   $initial,
        array                   $remove = [],
        ?FilterControlInterface $control = null,
        bool                    $debug = false,
    ): FormHarness {
        $driver = new class implements ListDriverInterface {
            public function resolveDcTable(string $type, array $config, array $attributes): string
            {
                return 'tl_test';
            }
        };

        $element = new class implements FilterElementInterface {
            public function buildEffect(
                FilterEffectBuilderInterface $builder,
                ElementContext               $context,
                ?ValueInterface              $value,
            ): void {}
        };

        $control ??= new KeywordsFilterControl();
        $list = new ListCompound(driver: $driver, type: 'test_list', dc: 'tl_test');
        $builder = Forms::createFormFactory()->createNamedBuilder('flare_test', FormType::class);
        $mounts = [];

        foreach ($initial as $alias => $keywords) {
            $filter = new FilterCompound(
                element: new ElementBinding($element, 'test_element', new ComponentConfig([]), KeywordsValue::class),
                alias: $alias,
                control: $control !== null ? new ControlBinding(
                    $control,
                    $control::class,
                    new ComponentConfig(['label' => null, 'placeholder' => null]),
                ) : null,
                value: $keywords === null ? null : new KeywordsValue($keywords),
            );

            $builder->add($alias, TextType::class, ['required' => false, 'data' => $keywords]);
            $context = new ControlContext($list, $filter, new ConfigurationIntent());
            $mounts[$alias] = new FilterMount($filter, $alias, $context);
        }

        foreach ($remove as $alias) {
            $builder->remove($alias);
        }

        $logs = &$this->logs;
        $logger = new class ($logs) extends AbstractLogger {
            public function __construct(private array &$logs) {}

            public function log($level, $message, array $context = []): void
            {
                $this->logs[] = [(string) $level, (string) $message];
            }
        };

        $translator = new class implements TranslatorInterface {
            use TranslatorTrait;
        };

        return new FormHarness($builder->getForm(), $mounts, $translator, $logger, $debug);
    }
}

/**
 * Rejects the keywords "rejected" in decode(), as a control does that cannot turn its data into a value, and fails
 * unexpectedly on "crashing".
 */
final class RejectingKeywordsControl extends KeywordsFilterControl
{
    public function decode(mixed $data, ControlContext $context): ?KeywordsValue
    {
        if ($data === 'rejected') {
            throw new FilterException('Rejected.', method: __METHOD__);
        }

        if ($data === 'crashing') {
            throw new \RuntimeException('Connection lost.');
        }

        return parent::decode($data, $context);
    }
}
