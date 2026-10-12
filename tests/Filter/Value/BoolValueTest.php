<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\Value;

use HeimrichHannot\FlareBundle\Enum\BoolBinaryChoices;
use HeimrichHannot\FlareBundle\Filter\Element\BooleanFilterElement;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use PHPUnit\Framework\TestCase;

final class BoolValueTest extends TestCase
{
    /**
     * `BoolValue::tryFrom()` is `BooleanFilterElement::normalizeValue()` lifted verbatim, so the two
     * must agree on every input for the later substitution to be behaviour-preserving. This is the
     * whole safety net for that move, so it runs the full input × choices matrix.
     *
     * `AbstractFilterElement` has no constructor — dependencies arrive via `#[Required]` setters —
     * and `normalizeValue()` touches none of them, so the element is constructible in a bare
     * TestCase with no kernel and no Contao boot.
     *
     * @dataProvider provideRawValues
     */
    public function testTryFromMatchesTheElementItReplaces(mixed $raw, ?BoolBinaryChoices $choices): void
    {
        $element = new BooleanFilterElement();

        self::assertSame(
            $element->normalizeValue($raw, $choices),
            BoolValue::tryFrom($raw, $choices)?->state,
            'BoolValue::tryFrom() diverged from BooleanFilterElement::normalizeValue().',
        );
    }

    /**
     * @return iterable<string, array{mixed, ?BoolBinaryChoices}>
     */
    public static function provideRawValues(): iterable
    {
        $inputs = [
            null, '', ' ', 'null', 'NULL', '0', '1', 'true', 'FALSE', 'yes', 'no', 'on', 'off',
            'garbage', 0, 1, -1, 0.0, true, false,
        ];

        foreach ($inputs as $input)
        {
            foreach ([null, ...BoolBinaryChoices::cases()] as $choices)
            {
                $label = \sprintf(
                    '%s %s / %s',
                    \get_debug_type($input),
                    \var_export($input, true),
                    $choices?->value ?? 'no choices',
                );

                yield $label => [$input, $choices];
            }
        }
    }

    /**
     * The §9 consequence: "no opinion" is the absence of a value, never an instance carrying null,
     * because two representations of one state cannot be normalised away by a constructor.
     */
    public function testNoOpinionIsTheAbsenceOfAValue(): void
    {
        self::assertNull(BoolValue::tryFrom(null));
        self::assertNull(BoolValue::tryFrom(''));
        self::assertNull(BoolValue::tryFrom('  NULL  '));
        self::assertNull(BoolValue::tryFrom('garbage'));
    }

    /** Under NULL_TRUE an unchecked box means "no opinion"; under the others it means false. */
    public function testBinaryChoicesDecideWhetherFalsyCollapses(): void
    {
        self::assertNull(BoolValue::tryFrom(false, BoolBinaryChoices::NULL_TRUE));
        self::assertFalse(BoolValue::tryFrom(false, BoolBinaryChoices::NULL_FALSE)?->state);
        self::assertFalse(BoolValue::tryFrom(false, BoolBinaryChoices::TRUE_FALSE)?->state);

        // Without a choice set — the `preselect` transformer's case — nothing collapses.
        self::assertFalse(BoolValue::tryFrom(false)?->state);
    }

    public function testTruthyStringsAndNumbersBecomeTrue(): void
    {
        self::assertTrue(BoolValue::tryFrom('1')?->state);
        self::assertTrue(BoolValue::tryFrom('true')?->state);
        self::assertTrue(BoolValue::tryFrom('YES')?->state);
        self::assertTrue(BoolValue::tryFrom(1)?->state);
    }
}
