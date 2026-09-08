<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Util;

/**
 * Flattens a value into an object-free, `serialize()`-stable structure for hashing.
 *
 * `serialize()` is a pure value function over a value *tree*, but not over an object *graph*: a
 * repeated object is emitted as a back-reference (`r:N;`), so two structures holding one shared
 * instance and two equal instances serialize differently. This was measured in
 * tests/Filter/ValueObjectSerializeProbeTest.php; see SPEC_FILTER_FORMS.md §14.1. Arrays and
 * scalars are never back-referenced, so a structure containing no objects at all cannot exhibit
 * the asymmetry — and that is what this class produces.
 *
 * Two further hazards are defused as a side effect: closures no longer make `serialize()` throw
 * (§9, probe finding #7), and PHP references inside arrays are copied by value, so no `R:N;` is
 * emitted either.
 *
 * What it deliberately does *not* do: sort arrays. §9's rule "anything with multiple equal
 * representations is normalized in the constructor" stays the value object's job — a flattener
 * cannot know whether an array's order is query-relevant.
 *
 * @see \HeimrichHannot\FlareBundle\Filter\Filter::fingerprint()
 */
final class Fingerprint
{
    /**
     * Depth 8 comfortably covers `Filter::$config` (2-3 levels) and every value object in
     * `src/Filter/Value/` (1-2 levels). Deeper structures degrade to a marker rather than
     * recursing without bound.
     */
    public const DEFAULT_MAX_DEPTH = 8;

    /** Reserved slot key. `*` is not valid in a PHP identifier, so no property name can collide. */
    private const KEY_CLASS = '*class*';
    private const MARK_DEPTH = '*depth-exceeded*';
    private const MARK_OPAQUE = '*opaque*';

    public static function flatten(mixed $value, int $maxDepth = self::DEFAULT_MAX_DEPTH): mixed
    {
        return self::walk($value, $maxDepth);
    }

    private static function walk(mixed $value, int $depth): mixed
    {
        if ($value === null || \is_bool($value) || \is_int($value) || \is_string($value)) {
            return $value;
        }

        if (\is_float($value))
        {
            // NAN !== NAN, and -0.0 == 0.0; both would make the hash non-reflexive.
            return \is_nan($value) ? [self::MARK_OPAQUE, 'float:nan'] : $value + 0.0;
        }

        if ($value instanceof \UnitEnum)
        {
            // Enum cases are singletons, so identity is already value — but the class name must
            // still be carried, or two enums sharing a case name would collide.
            return [$value::class, $value instanceof \BackedEnum ? $value->value : $value->name];
        }

        if ($depth <= 0) {
            return [self::MARK_DEPTH, \get_debug_type($value)];
        }

        if (\is_array($value))
        {
            $flattened = [];

            foreach ($value as $key => $item) {
                $flattened[$key] = self::walk($item, $depth - 1);
            }

            return $flattened;
        }

        if ($value instanceof \Closure)
        {
            // A closure is not serializable at all. Its definition site is the closest thing to a
            // pure value it has: deterministic, free of object identity, and it distinguishes
            // closures declared at different code positions — the realistic case. Two closures
            // from the same line with different bound state collide, which is a cache miss's worth
            // of wrongness where the unflattened behaviour is an uncaught exception.
            $reflection = new \ReflectionFunction($value);

            return [self::MARK_OPAQUE, \Closure::class, $reflection->getFileName(), $reflection->getStartLine()];
        }

        if (\is_object($value))
        {
            // get_object_vars() from outside the class returns *public* properties only, in
            // declaration order (stable per class). That is precisely the surface the §9
            // containment rule constrains — see ValueObjectContainmentTest, which asserts every
            // value-object property is public so nothing can hide from this walk.
            $flattened = [self::KEY_CLASS => $value::class];

            foreach (\get_object_vars($value) as $name => $property) {
                $flattened[$name] = self::walk($property, $depth - 1);
            }

            return $flattened;
        }

        // Resources and anything else with no value semantics.
        return [self::MARK_OPAQUE, \get_debug_type($value)];
    }
}
