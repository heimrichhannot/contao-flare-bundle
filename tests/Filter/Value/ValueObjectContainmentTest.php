<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\Value;

use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use HeimrichHannot\FlareBundle\Filter\Value\ChoiceValue;
use HeimrichHannot\FlareBundle\Filter\Value\DateRangeValue;
use HeimrichHannot\FlareBundle\Filter\Value\KeywordsValue;
use HeimrichHannot\FlareBundle\Filter\Value\ParentRefValue;
use PHPUnit\Framework\TestCase;

/**
 * SPEC_FILTER_FORMS.md §10, row 4: enforces the §9 containment rule by reflection, so it cannot
 * drift from the real properties the way a hand-written `fingerprint()` method would.
 *
 * Discovery has two sources, unioned: `src/Filter/Value/*.php`, which is what makes this useful
 * before any element declares a value class, and every `#[AsFilterElement(value: …)]`, which starts
 * contributing later with no change to this file. The attribute is read via
 * `ReflectionAttribute::getArguments()` rather than through the container, because
 * `RegisterFilterElementsPass` reconstructs the attribute from a fixed argument list — reflection
 * sees a declaration the container may not.
 *
 * "final readonly" is not the marker: `Filter`, `ListSpec`, `FilterData`, `FilterSet` and
 * `FilterContext` are all final readonly too, and `Filter` holds a `FilterElementInterface`. The
 * namespace is the marker.
 */
final class ValueObjectContainmentTest extends TestCase
{
    private const VALUE_NAMESPACE = 'HeimrichHannot\\FlareBundle\\Filter\\Value\\';

    /** Directories that may declare `#[AsFilterElement]`. */
    private const ELEMENT_DIRS = ['src/Filter/Element', 'src/Integration/CodefogTags/FilterElement'];

    private const SCALARS = ['bool', 'int', 'float', 'string', 'null'];

    /** Leaf grammar for `@var` on an `array` property: scalars, enums, or value objects. */
    private const LEAF = '(?:bool|int|float|string|null|non-empty-string|positive-int|\\\\?[A-Z][A-Za-z0-9_\\\\]*)';

    /** @var array<class-string, true> Cycle guard: value-object *types* may be mutually recursive. */
    private array $checked = [];

    public function testEveryValueObjectSatisfiesTheContainmentRule(): void
    {
        $classes = self::discoverValueClasses();

        self::assertNotSame([], $classes, 'No value objects found in src/Filter/Value/.');

        foreach ($classes as $class) {
            $this->assertConformingValueObject($class);
        }
    }

    /**
     * Closes the reflection test's own blind spot. A property typed `array` could hold a
     * `\DateTimeImmutable` or a Contao model without any signature saying so — the exact hazards §9
     * names. Every value object therefore contributes a representative instance, walked recursively
     * at runtime.
     */
    public function testEveryValueObjectHasASampleContainingOnlyConformingValues(): void
    {
        $samples = self::samples();

        self::assertSame(
            self::discoverValueClasses(),
            \array_keys($samples),
            'Add a sample to self::samples() for every class in src/Filter/Value/.',
        );

        foreach ($samples as $class => $sample) {
            self::assertConformingRuntimeValue($sample, $class);
        }
    }

    /**
     * §10 row 4 proper. While no element declares `value:` this asserts the scan works and passes
     * vacuously; once they do, it is the enforcement gate.
     */
    public function testDeclaredElementValueClassesAreConformingValueObjects(): void
    {
        $elements = self::discoverElementClasses();

        self::assertNotSame([], $elements, 'No filter elements found — the scan is broken.');

        foreach ($elements as $element)
        {
            foreach ((new \ReflectionClass($element))->getAttributes(AsFilterElement::class) as $attribute)
            {
                $arguments = $attribute->getArguments();

                if (!\array_key_exists('value', $arguments)) {
                    continue;
                }

                $value = $arguments['value'];

                if ($value === null) {
                    continue;  // §5.3: no value object => intrinsic-only.
                }

                self::assertIsString($value, $element . ': AsFilterElement::$value must be a class name or null.');
                self::assertStringStartsWith(
                    self::VALUE_NAMESPACE,
                    $value,
                    \sprintf('%s declares value "%s", which is not in %s.', $element, $value, self::VALUE_NAMESPACE),
                );

                $this->assertConformingValueObject($value);
            }
        }
    }

    // ------------------------------------------------------------------ assertions

    private function assertConformingValueObject(string $class): void
    {
        if (isset($this->checked[$class])) {
            return;
        }

        $this->checked[$class] = true;

        self::assertTrue(\class_exists($class), \sprintf('Value class "%s" does not exist.', $class));

        $reflection = new \ReflectionClass($class);

        self::assertTrue($reflection->isFinal(), $class . ' must be final (SPEC §9).');
        self::assertTrue($reflection->isReadOnly(), $class . ' must be readonly (SPEC §9).');
        self::assertFalse($reflection->isAbstract(), $class . ' must be concrete.');

        foreach ($reflection->getProperties() as $property)
        {
            $what = $class . '::$' . $property->getName();

            self::assertFalse($property->isStatic(), $what . ' must not be static.');
            self::assertTrue(
                $property->isPublic(),
                $what . ' must be public: Fingerprint::flatten() reads public properties only, so a'
                    . ' private property would be invisible to the hash.',
            );
            self::assertTrue($property->hasType(), $what . ' must declare a type (SPEC §9).');

            $this->assertContainmentType($property->getType(), $what);

            if (self::declaresArray($property->getType())) {
                self::assertArrayElementDocblock($property, $what);
            }
        }
    }

    private function assertContainmentType(?\ReflectionType $type, string $what, int $depth = 0): void
    {
        self::assertNotNull($type, $what . ' must declare a type.');
        self::assertLessThan(4, $depth, $what . ': type nesting too deep to check.');

        // Intersection types can only combine interfaces, which never conform.
        self::assertNotInstanceOf(
            \ReflectionIntersectionType::class,
            $type,
            $what . ' must not use an intersection type (SPEC §9).',
        );

        if ($type instanceof \ReflectionUnionType)
        {
            foreach ($type->getTypes() as $member) {
                $this->assertContainmentType($member, $what, $depth + 1);
            }

            return;
        }

        self::assertInstanceOf(\ReflectionNamedType::class, $type, $what . ' has an unsupported type.');

        $name = $type->getName();

        if (\in_array($name, self::SCALARS, true) || $name === 'array') {
            return;
        }

        self::assertTrue(
            \enum_exists($name) || \class_exists($name),
            \sprintf('%s: type "%s" does not exist.', $what, $name),
        );

        if (\enum_exists($name)) {
            return;
        }

        self::assertStringStartsWith(
            self::VALUE_NAMESPACE,
            $name,
            \sprintf(
                '%s: type "%s" is neither a scalar, null, an enum, nor a nested filter value object'
                . ' (SPEC_FILTER_FORMS.md §9 containment rule). Store its id or a timestamp instead.',
                $what,
                $name,
            ),
        );

        $this->assertConformingValueObject($name);
    }

    private static function assertArrayElementDocblock(\ReflectionProperty $property, string $what): void
    {
        // Normalising constructors preclude property promotion, so `@var` sits on the property
        // itself and is reliably reflectable.
        $doc = $property->getDocComment();

        self::assertNotFalse(
            $doc,
            $what . ': an `array` property must document its element type with @var, because the'
                . ' native type erases it (SPEC §9).',
        );

        $pattern = \sprintf('/@var\s+((?:list<%1$s>|array<%1$s,\s*(?:%1$s|list<%1$s>)>))/', self::LEAF);

        self::assertMatchesRegularExpression(
            $pattern,
            $doc,
            $what . ': @var must match the containment grammar — list<leaf> or'
                . ' array<leaf, leaf|list<leaf>>, where leaf is a scalar, an enum or a value object.',
        );

        // Scan the captured type expression only. The rest of the docblock is prose, and a
        // capitalised word in it ("Non-empty, …") is not a type.
        \preg_match($pattern, $doc, $captured);
        \preg_match_all('/\\\\?[A-Z][A-Za-z0-9_\\\\]*/', $captured[1], $matches);

        foreach ($matches[0] as $leaf)
        {
            $leaf = \ltrim($leaf, '\\');

            self::assertTrue(
                \enum_exists($leaf) || \str_starts_with($leaf, self::VALUE_NAMESPACE),
                \sprintf('%s: @var element type "%s" is neither an enum nor a value object.', $what, $leaf),
            );
        }
    }

    private static function assertConformingRuntimeValue(mixed $value, string $path): void
    {
        if ($value === null || \is_scalar($value) || $value instanceof \UnitEnum) {
            return;
        }

        if (\is_array($value))
        {
            foreach ($value as $key => $item)
            {
                self::assertTrue(\is_int($key) || \is_string($key), $path . ': array keys must be int or string.');
                self::assertConformingRuntimeValue($item, \sprintf('%s[%s]', $path, $key));
            }

            return;
        }

        self::assertTrue(
            \is_object($value) && \str_starts_with($value::class, self::VALUE_NAMESPACE),
            \sprintf(
                '%s holds a %s, which is neither a scalar, null, an enum, nor a nested filter value'
                . ' object (SPEC §9). Store its id or a timestamp.',
                $path,
                \get_debug_type($value),
            ),
        );

        foreach (\get_object_vars($value) as $name => $property) {
            self::assertConformingRuntimeValue($property, $path . '->' . $name);
        }
    }

    // ------------------------------------------------------------------ discovery

    private static function declaresArray(?\ReflectionType $type): bool
    {
        if ($type instanceof \ReflectionNamedType) {
            return $type->getName() === 'array';
        }

        if ($type instanceof \ReflectionUnionType)
        {
            foreach ($type->getTypes() as $member)
            {
                if (self::declaresArray($member)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return list<class-string>
     */
    private static function discoverValueClasses(): array
    {
        $classes = [];

        foreach (\glob(self::projectDir() . '/src/Filter/Value/*.php') ?: [] as $file) {
            $classes[] = self::VALUE_NAMESPACE . \basename($file, '.php');
        }

        \sort($classes, \SORT_STRING);

        return $classes;
    }

    /**
     * @return list<class-string>
     */
    private static function discoverElementClasses(): array
    {
        $classes = [];

        foreach (self::ELEMENT_DIRS as $dir)
        {
            foreach (\glob(self::projectDir() . '/' . $dir . '/*.php') ?: [] as $file)
            {
                $class = 'HeimrichHannot\\FlareBundle\\'
                    . \str_replace('/', '\\', \substr($dir, \strlen('src/')))
                    . '\\' . \basename($file, '.php');

                // An element whose optional vendor dependency is absent must not fail the scan.
                try
                {
                    if (!\class_exists($class)) {
                        continue;
                    }
                }
                catch (\Throwable)
                {
                    continue;
                }

                if ((new \ReflectionClass($class))->getAttributes(AsFilterElement::class)) {
                    $classes[] = $class;
                }
            }
        }

        \sort($classes, \SORT_STRING);

        return $classes;
    }

    private static function projectDir(): string
    {
        return \dirname(__DIR__, 3);
    }

    /**
     * One representative, fully populated instance per value object. Keyed and ordered to match
     * self::discoverValueClasses(), which the test above asserts.
     *
     * @return array<class-string, object>
     */
    private static function samples(): array
    {
        return [
            BoolValue::class => new BoolValue(true),
            ChoiceValue::class => new ChoiceValue(['b', 'a', 'a', '']),
            DateRangeValue::class => new DateRangeValue(1_767_225_600, 1_767_312_000),
            KeywordsValue::class => new KeywordsValue('  foo   OR  bar '),
            ParentRefValue::class => new ParentRefValue(['tl_news_archive' => [5, 3, 3, 0]]),
        ];
    }
}
