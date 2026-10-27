<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Form;

use HeimrichHannot\FlareBundle\Config\ComponentConfig;
use HeimrichHannot\FlareBundle\Engine\Context\ConfigurationContext;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\Control\FilterControlInterface;
use HeimrichHannot\FlareBundle\Filter\Control\KeywordsFilterControl;
use HeimrichHannot\FlareBundle\Filter\ControlBinding;
use HeimrichHannot\FlareBundle\Filter\ControlScope;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\ElementBinding;
use HeimrichHannot\FlareBundle\Filter\ElementScope;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Value\KeywordsValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Form\FilterMount;
use HeimrichHannot\FlareBundle\Form\FormHarness;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\ListSpec;
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

    /**
     * @param array<string, ?string> $initial Alias => initial keywords (null: no initial value).
     * @param list<string> $remove Aliases whose child is removed from the form after mounting.
     */
    private function harness(
        array                   $initial,
        array                   $remove = [],
        ?FilterControlInterface $control = null,
    ): FormHarness {
        $driver = new class implements ListDriverInterface {
            public function resolveDcTable(string $type, array $config, array $attributes): string
            {
                return 'tl_test';
            }
        };

        $element = new class implements FilterElementInterface {
            public function buildContext(
                FilterContextBuilderInterface $builder,
                ElementScope                  $scope,
                ?ValueInterface               $value,
            ): void {}
        };

        $control ??= new KeywordsFilterControl();
        $list = new ListSpec(driver: $driver, type: 'test_list', dc: 'tl_test');
        $builder = Forms::createFormFactory()->createNamedBuilder('flare_test', FormType::class);
        $mounts = [];

        foreach ($initial as $alias => $keywords) {
            $filter = new Filter(
                element: new ElementBinding($element, 'test_element', new ComponentConfig([])),
                alias: $alias,
                control: $control !== null ? new ControlBinding(
                    $control,
                    $control::class,
                    new ComponentConfig(['label' => null, 'placeholder' => null]),
                ) : null,
                value: $keywords === null ? null : new KeywordsValue($keywords),
            );

            $builder->add($alias, TextType::class, ['required' => false, 'data' => $keywords]);
            $scope = new ControlScope($list, $filter, new ConfigurationContext());
            $mounts[$alias] = new FilterMount($filter, $alias, $scope);
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

        return new FormHarness($builder->getForm(), $mounts, $translator, $logger);
    }
}

/**
 * Rejects the keywords "rejected" in decode(), as a control does that cannot turn its data into a value.
 */
final class RejectingKeywordsControl extends KeywordsFilterControl
{
    public function decode(mixed $data, ControlScope $scope): ?KeywordsValue
    {
        if ($data === 'rejected') {
            throw new FilterException('Rejected.', method: __METHOD__);
        }

        return parent::decode($data, $scope);
    }
}
