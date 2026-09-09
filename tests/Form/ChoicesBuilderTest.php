<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Form;

use HeimrichHannot\FlareBundle\Contract\LabelableInterface;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Characterisation tests: they pin `ChoicesBuilder`'s behaviour *as it is today*, so the planned
 * change to `add()` — making `choice` carry identity rather than the display string, which fixes
 * the duplicate-label collision — has a safety net. Several assertions below therefore document
 * quirks rather than endorse them.
 *
 * Scope: string and {@see LabelableInterface} choices only. Every `Contao\Model` label path needs
 * `$GLOBALS['TL_MODELS']` and `Model::getClassFromTable()`, and this suite deliberately boots
 * neither a kernel nor the Contao framework, so model label resolution stays uncovered.
 */
final class ChoicesBuilderTest extends TestCase
{
    public function testBuildChoicesPreservesInsertionOrderAndAddsNothingByDefault(): void
    {
        $builder = self::builder()->add('b', 'Beta')->add('a', 'Alpha');

        self::assertSame(['b' => 'Beta', 'a' => 'Alpha'], $builder->buildChoices());
        self::assertFalse($builder->hasEmptyOption());
    }

    /** The sentinel is prepended, so its position in the rendered widget is first. */
    public function testEmptyOptionIsPrependedAsTheFirstKey(): void
    {
        $builder = self::builder()->add('a', 'Alpha')->setEmptyOption(true);

        self::assertSame(
            [ChoicesBuilder::EMPTY_CHOICE, 'a'],
            \array_keys($builder->buildChoices()),
        );
        self::assertSame(ChoicesBuilder::EMPTY_CHOICE, $builder->buildChoices()[ChoicesBuilder::EMPTY_CHOICE]);
    }

    /**
     * `count()` counts the real choices only, unlike `buildChoices()`. `ArchiveFilterElement` relies
     * on exactly this to decide whether any whitelisted parent survived.
     */
    public function testCountExcludesTheEmptyOption(): void
    {
        $builder = self::builder()->add('a', 'Alpha')->add('b', 'Beta')->setEmptyOption(true);

        self::assertSame(2, $builder->count());
        self::assertCount(3, $builder->buildChoices());
    }

    /** Plain array re-assignment, so a re-added alias keeps its original position. */
    public function testReAddingAnAliasOverwritesInPlace(): void
    {
        $builder = self::builder()->add('a', 'Alpha')->add('b', 'Beta')->add('a', 'Alpha2');

        self::assertSame(['a' => 'Alpha2', 'b' => 'Beta'], $builder->buildChoices());
        self::assertSame(2, $builder->count());
    }

    public function testGetChoiceRoundTripsAndReturnsNullForUnknownKeys(): void
    {
        $builder = self::builder()->add('a', 'Alpha');

        self::assertSame('Alpha', $builder->getChoice('a'));
        self::assertNull($builder->getChoice('nope'));
    }

    public function testChoiceValueCallbackPrefersAnExplicitValueOverTheAlias(): void
    {
        $toValue = self::builder()->add('a', 'Alpha')->add('b', 'Beta', 42)->buildChoiceValueCallback();

        self::assertSame('a', $toValue('Alpha'));
        self::assertSame('42', $toValue('Beta'));
    }

    public function testChoiceValueCallbackMapsTheSentinelToTheEmptyOptionValue(): void
    {
        $default = self::builder()->setEmptyOption(true)->buildChoiceValueCallback();

        self::assertSame(ChoicesBuilder::EMPTY_CHOICE_VALUE_DEFAULT, $default(ChoicesBuilder::EMPTY_CHOICE));

        $alternative = self::builder()
            ->setEmptyOption(true, ChoicesBuilder::EMPTY_CHOICE_VALUE_ALTERNATIVE)
            ->buildChoiceValueCallback();

        self::assertSame(
            ChoicesBuilder::EMPTY_CHOICE_VALUE_ALTERNATIVE,
            $alternative(ChoicesBuilder::EMPTY_CHOICE),
        );
    }

    public function testChoiceValueCallbackReturnsAnEmptyStringForUnknownChoices(): void
    {
        self::assertSame('', (self::builder()->add('a', 'Alpha')->buildChoiceValueCallback())('Missing'));
    }

    /**
     * The reverse lookup is a strict `array_search`, so an int `1` does not match the string `'1'`.
     * This strictness is what lets `ArchiveFilterElement` get Contao model *instances* back by
     * identity — and it is also why a scalar type mismatch silently yields `''`.
     */
    public function testChoiceValueCallbackIsStrictAboutTypes(): void
    {
        $toValue = self::builder()->add('a', '1')->buildChoiceValueCallback();

        self::assertSame('a', $toValue('1'));
        self::assertSame('', $toValue(1));
    }

    /**
     * Two choices sharing a display string collapse onto the first alias, because the reverse
     * lookup searches by the choice rather than by identity. This is the §7.1 defect; the assertion
     * records the current behaviour so the fix is visible as a diff to this test.
     */
    public function testDuplicateDisplayStringsCollapseOntoTheFirstAlias(): void
    {
        $toValue = self::builder()->add('a', 'Same')->add('b', 'Same')->buildChoiceValueCallback();

        self::assertSame('a', $toValue('Same'));
    }

    /**
     * The deferral contract `ArchiveFilterElement` and `CodefogTagsChoiceFilterElement` depend on:
     * both call `applyFormOptions()` *before* populating choices, which only works because the
     * loader closes over `buildChoices(...)` rather than a snapshot. Breaking this makes the archive
     * filter silently lose every option.
     */
    public function testApplyFormOptionsInstallsADeferredChoiceLoader(): void
    {
        $builder = self::builder();
        $options = [];

        $builder->applyFormOptions($options);

        // Every choice is added only after the loader was installed.
        $builder->add('late', 'Late');
        $builder->add('later', 'Later');

        self::assertInstanceOf(CallbackChoiceLoader::class, $options['choice_loader']);

        $choices = $options['choice_loader']->loadChoiceList($options['choice_value'])->getChoices();

        self::assertSame(['Late', 'Later'], \array_values($choices));
    }

    public function testApplyFormOptionsMutatesByReferenceAndIsFluent(): void
    {
        $builder = self::builder();
        $options = ['label' => 'Untouched'];

        $returned = $builder->applyFormOptions($options);

        self::assertSame($builder, $returned);
        self::assertSame('Untouched', $options['label']);
        self::assertSame(
            ['label', 'choice_loader', 'choice_label', 'choice_value'],
            \array_keys($options),
        );
    }

    public function testBuildFormOptionsReturnsExactlyTheThreeChoiceKeys(): void
    {
        self::assertSame(
            ['choice_loader', 'choice_label', 'choice_value'],
            \array_keys(self::builder()->buildFormOptions()),
        );
    }

    /**
     * The empty option is keyed differently by the two builders: `EMPTY_CHOICE` for the Symfony
     * choice list, `''` for the Contao options array. Pinned so nobody unifies them by accident.
     */
    public function testEmptyOptionKeyDiffersBetweenSymfonyAndContaoOutput(): void
    {
        $builder = self::builder()->add('a', 'Alpha')->setEmptyOption(true);

        self::assertArrayHasKey(ChoicesBuilder::EMPTY_CHOICE, $builder->buildChoices());
        self::assertArrayNotHasKey('', $builder->buildChoices());

        self::assertArrayHasKey('', $builder->buildContaoOptions());
        self::assertArrayNotHasKey(ChoicesBuilder::EMPTY_CHOICE, $builder->buildContaoOptions());
    }

    /** A string choice is translated as the message id itself, in the `flare_form` domain. */
    public function testStringChoicesAreTranslatedAsTheirOwnMessageId(): void
    {
        $recorded = [];
        $builder = new ChoicesBuilder(self::translator($recorded), self::parameterBag());
        $label = $builder->buildChoiceLabelCallback();

        self::assertSame('Alpha', $label('Alpha', 'a', 'a'));
        self::assertSame([['Alpha', 'flare_form']], $recorded);
    }

    public function testEmptyOptionLabelIsUsedForTheSentinelKey(): void
    {
        $builder = self::builder()->setEmptyOption('empty_option.prompt');
        $label = $builder->buildChoiceLabelCallback();

        self::assertSame('empty_option.prompt', $label(null, ChoicesBuilder::EMPTY_CHOICE, ''));
    }

    /** A null choice falls back to the ndash message rather than rendering as an empty label. */
    public function testNullChoiceFallsBackToTheNdashMessage(): void
    {
        self::assertSame('empty_option.ndash', self::builder()->buildChoiceLabel(null, '', ''));
    }

    public function testLabelableParametersReachTheTranslator(): void
    {
        $recorded = [];
        $builder = new ChoicesBuilder(self::translator($recorded), self::parameterBag());
        $builder->setLabel('label.custom');

        $choice = new class implements LabelableInterface {
            public function getLabelParameters(): array
            {
                return ['%name%' => 'Widget'];
            }
        };

        self::assertSame('label.custom', $builder->buildChoiceLabel($choice, 'k', 'v'));
        self::assertSame([['label.custom', 'flare_form']], $recorded);
    }

    public function testSetModelSuffixRoundTrips(): void
    {
        self::assertSame('', self::builder()->getModelSuffix());
        self::assertSame('(%@name%)', self::builder()->setModelSuffix('(%@name%)')->getModelSuffix());
    }

    /** `setEmptyOption()` with a label turns the option on as a side effect. */
    public function testSetEmptyOptionWithALabelEnablesIt(): void
    {
        self::assertTrue(self::builder()->setEmptyOption('some.label')->hasEmptyOption());
        self::assertFalse(self::builder()->setEmptyOption(false)->hasEmptyOption());
    }

    private static function builder(): ChoicesBuilder
    {
        $recorded = [];

        return new ChoicesBuilder(self::translator($recorded), self::parameterBag());
    }

    /**
     * Returns the message id verbatim, so assertions read as the key that would be translated.
     *
     * @param list<array{string, string}> $recorded Receives [id, domain] per call.
     */
    private static function translator(array &$recorded): TranslatorInterface
    {
        return new class ($recorded) implements TranslatorInterface {
            /** @param list<array{string, string}> $recorded */
            public function __construct(private array &$recorded) {}

            public function trans(?string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                $this->recorded[] = [(string) $id, (string) $domain];

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
