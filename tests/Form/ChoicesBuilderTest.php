<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Form;

use HeimrichHannot\FlareBundle\Contract\LabelableInterface;
use HeimrichHannot\FlareBundle\Filter\Value\Choice\ContaoModelChoice;
use HeimrichHannot\FlareBundle\Filter\Value\Choice\ValueChoice;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilder;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Contract tests for `ChoicesBuilder` on choice objects. Model label resolution that needs a loaded
 * `Contao\Model` stays uncovered: this suite boots neither a kernel nor the Contao framework.
 */
final class ChoicesBuilderTest extends TestCase
{
    public function testBuildChoicesPreservesInsertionOrderAndAddsNothingByDefault(): void
    {
        $b = new ValueChoice('b', 'Beta');
        $a = new ValueChoice('a', 'Alpha');
        $builder = self::builder()->add($b)->add($a);

        self::assertSame(['b' => $b, 'a' => $a], $builder->buildChoices());
        self::assertFalse($builder->hasEmptyOption());
    }

    public function testEmptyOptionIsPrependedAsTheFirstKey(): void
    {
        $builder = self::builder()->add(new ValueChoice('a'))->setEmptyOption(true);

        self::assertSame([ChoicesBuilder::EMPTY_CHOICE, 'a'], \array_keys($builder->buildChoices()));
        self::assertSame(ChoicesBuilder::EMPTY_CHOICE, $builder->buildChoices()[ChoicesBuilder::EMPTY_CHOICE]);
    }

    public function testCountExcludesTheEmptyOption(): void
    {
        $builder = self::builder()->add(new ValueChoice('a'))->add(new ValueChoice('b'))->setEmptyOption(true);

        self::assertSame(2, $builder->count());
        self::assertCount(3, $builder->buildChoices());
    }

    public function testReAddingAKeyOverwritesInPlace(): void
    {
        $second = new ValueChoice('a', 'Alpha2');
        $builder = self::builder()->add(new ValueChoice('a', 'Alpha'))->add(new ValueChoice('b'))->add($second);

        self::assertSame(['a', 'b'], \array_keys($builder->buildChoices()));
        self::assertSame($second, $builder->buildChoices()['a']);
        self::assertSame(2, $builder->count());
    }

    public function testEqualLabelsKeepDistinctSubmittedValues(): void
    {
        $builder = self::builder()->add(new ValueChoice('1', 'Same'))->add(new ValueChoice('2', 'Same'));
        $toValue = $builder->buildChoiceValueCallback();
        self::assertSame(['1', '2'], \array_map($toValue, \array_values($builder->buildChoices())));
    }

    public function testSingleScopeListSubmitsLocalKeys(): void
    {
        $builder = self::builder()->add(ContaoModelChoice::ref('tl_news_archive', 5));
        self::assertTrue($builder->usesLocalKeys());
        self::assertSame('5', ($builder->buildChoiceValueCallback())(ContaoModelChoice::ref('tl_news_archive', 5)));
    }

    public function testMixedScopesSubmitCanonicalKeys(): void
    {
        $builder = self::builder()->add(ContaoModelChoice::ref('tl_a', 1))->add(ContaoModelChoice::ref('tl_b', 1));
        self::assertFalse($builder->usesLocalKeys());
        self::assertSame('tl_b.1', ($builder->buildChoiceValueCallback())(ContaoModelChoice::ref('tl_b', 1)));
    }

    public function testContaoOptionsAreKeyedByCanonicalKey(): void
    {
        $options = self::builder()->add(ContaoModelChoice::ref('tl_a', 1))->buildContaoOptions();
        self::assertSame(['tl_a.1'], \array_keys($options));
        self::assertSame('tl_a.1', $options['tl_a.1']);
    }

    public function testChoiceValueCallbackMapsTheSentinelToTheEmptyOptionValue(): void
    {
        $default = self::builder()->setEmptyOption(true)->buildChoiceValueCallback();

        self::assertSame(ChoicesBuilder::EMPTY_CHOICE_VALUE_DEFAULT, $default(ChoicesBuilder::EMPTY_CHOICE));

        $alternative = self::builder()
            ->setEmptyOption(true, ChoicesBuilder::EMPTY_CHOICE_VALUE_ALTERNATIVE)
            ->buildChoiceValueCallback();

        self::assertSame(ChoicesBuilder::EMPTY_CHOICE_VALUE_ALTERNATIVE, $alternative(ChoicesBuilder::EMPTY_CHOICE));
    }

    public function testChoiceValueCallbackReturnsAnEmptyStringForNonChoices(): void
    {
        self::assertSame('', (self::builder()->buildChoiceValueCallback())('Missing'));
        self::assertSame('', (self::builder()->buildChoiceValueCallback())(null));
    }

    public function testChoiceValueCallbackNeedsNoListLookup(): void
    {
        $toValue = self::builder()->add(new ValueChoice('a'))->buildChoiceValueCallback();

        self::assertSame('a', $toValue(new ValueChoice('a')));
        self::assertSame('zzz', $toValue(new ValueChoice('zzz')));
    }

    public function testApplyFormOptionsInstallsADeferredChoiceLoader(): void
    {
        $builder = self::builder();
        $options = [];

        $builder->applyFormOptions($options);

        $builder->add(new ValueChoice('late'));
        $builder->add(new ValueChoice('later'));

        self::assertInstanceOf(CallbackChoiceLoader::class, $options['choice_loader']);

        $choices = $options['choice_loader']->loadChoiceList($options['choice_value'])->getChoices();

        self::assertSame(['late', 'later'], \array_keys($choices));
    }

    public function testApplyFormOptionsMutatesByReferenceAndIsFluent(): void
    {
        $builder = self::builder();
        $options = ['label' => 'Untouched'];

        $returned = $builder->applyFormOptions($options);

        self::assertSame($builder, $returned);
        self::assertSame('Untouched', $options['label']);
        self::assertSame(['label', 'choice_loader', 'choice_label', 'choice_value'], \array_keys($options));
    }

    public function testBuildFormOptionsReturnsExactlyTheThreeChoiceKeys(): void
    {
        self::assertSame(
            ['choice_loader', 'choice_label', 'choice_value'],
            \array_keys(self::builder()->buildFormOptions()),
        );
    }

    public function testEmptyOptionKeyDiffersBetweenSymfonyAndContaoOutput(): void
    {
        $builder = self::builder()->add(new ValueChoice('a'))->setEmptyOption(true);

        self::assertArrayHasKey(ChoicesBuilder::EMPTY_CHOICE, $builder->buildChoices());
        self::assertArrayNotHasKey('', $builder->buildChoices());

        self::assertArrayHasKey('', $builder->buildContaoOptions());
        self::assertArrayNotHasKey(ChoicesBuilder::EMPTY_CHOICE, $builder->buildContaoOptions());
    }

    public function testValueChoiceLabelIsTranslatedWithItsPlaceholders(): void
    {
        $recorded = [];
        $builder = new ChoicesBuilder(self::translator($recorded), self::parameterBag());
        $label = $builder->buildChoiceLabelCallback();

        self::assertSame('Alpha', $label(new ValueChoice('a', 'Alpha'), 'a', 'a'));
        self::assertSame([['Alpha', 'flare_form', ['%value%' => 'a']]], self::withoutAt($recorded));
    }

    public function testValueChoiceWithoutLabelFallsBackToItsValue(): void
    {
        self::assertSame('a', self::builder()->buildChoiceLabel(new ValueChoice('a'), 'a', 'a'));
    }

    public function testModellessModelChoiceLabelIsTableDotId(): void
    {
        self::assertSame(
            'tl_a.7',
            self::builder()->buildChoiceLabel(ContaoModelChoice::ref('tl_a', 7), 'tl_a.7', '7'),
        );
    }

    public function testModelChoiceLabelReceivesTheScalarRowFieldsAndTheTable(): void
    {
        $model = new class(['id' => 7, 'title' => 'News', 'meta' => ['x']]) extends FilterModel {
            /** @noinspection PhpMissingParentConstructorInspection */
            public function __construct(array $row)
            {
                $this->arrData = $row;
            }
        };

        $recorded = [];
        $builder = new ChoicesBuilder(self::translator($recorded), self::parameterBag());
        $builder->buildChoiceLabel(ContaoModelChoice::from($model), 'tl_flare_filter.7', '7');

        $params = \end($recorded)[2];

        self::assertSame('News', $params['%title%']);
        self::assertSame(7, $params['%id%']);
        self::assertSame('tl_flare_filter', $params['%@table%']);
        self::assertArrayNotHasKey('%meta%', $params);
    }

    public function testEmptyOptionLabelIsUsedForTheSentinelKey(): void
    {
        $builder = self::builder()->setEmptyOption('empty_option.prompt');
        $label = $builder->buildChoiceLabelCallback();

        self::assertSame('empty_option.prompt', $label(null, ChoicesBuilder::EMPTY_CHOICE, ''));
    }

    public function testNullChoiceFallsBackToTheNdashMessage(): void
    {
        self::assertSame('empty_option.ndash', self::builder()->buildChoiceLabel(null, '', ''));
    }

    public function testLabelableParametersReachTheTranslator(): void
    {
        $recorded = [];
        $builder = new ChoicesBuilder(self::translator($recorded), self::parameterBag());
        $builder->setEmptyOption(new class implements LabelableInterface {
            public function getLabelParameters(): array
            {
                return ['%name%' => 'Widget'];
            }
        });

        $builder->buildChoiceLabelCallback()(null, ChoicesBuilder::EMPTY_CHOICE, '');

        self::assertSame('Widget', $recorded[0][2]['%name%']);
    }

    public function testSetModelSuffixRoundTrips(): void
    {
        self::assertSame('', self::builder()->getModelSuffix());
        self::assertSame('(%@name%)', self::builder()->setModelSuffix('(%@name%)')->getModelSuffix());
    }

    public function testSetEmptyOptionWithALabelEnablesIt(): void
    {
        self::assertTrue(self::builder()->setEmptyOption('some.label')->hasEmptyOption());
        self::assertFalse(self::builder()->setEmptyOption(false)->hasEmptyOption());
    }

    /**
     * @param list<array{string, string, array}> $recorded
     * @return list<array{string, string, array}>
     */
    private static function withoutAt(array $recorded): array
    {
        return \array_map(
            static fn (array $r): array => [$r[0], $r[1], \array_filter($r[2], static fn ($k) => !\str_starts_with($k, '%@'), \ARRAY_FILTER_USE_KEY)],
            $recorded,
        );
    }

    private static function builder(): ChoicesBuilder
    {
        $recorded = [];

        return new ChoicesBuilder(self::translator($recorded), self::parameterBag());
    }

    /**
     * Returns the message id verbatim, so assertions read as the key that would be translated.
     *
     * @param list<array{string, string, array}> $recorded Receives [id, domain] per call.
     */
    private static function translator(array &$recorded): TranslatorInterface
    {
        return new class ($recorded) implements TranslatorInterface {
            /** @param list<array{string, string, array}> $recorded */
            public function __construct(private array &$recorded) {}

            public function trans(?string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                $this->recorded[] = [(string) $id, (string) $domain, $parameters];

                return (string) $id;
            }

            public function getLocale(): string
            {
                return 'en';
            }
        };
    }

    private static function parameterBag(): ParameterBag
    {
        // tryGetDefaultTypeLabel() reads this parameter unguarded, and the extension always sets
        // it; an absent key would throw rather than fall back.
        return new ParameterBag(['huh_flare.format_label_defaults' => []]);
    }
}
