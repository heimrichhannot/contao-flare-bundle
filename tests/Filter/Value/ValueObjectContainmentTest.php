<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\Value;

use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use PHPUnit\Framework\TestCase;

/**
 * Every concrete class in `src/Filter/Value/` and every value class named in an `#[AsFilterElement(value: …)]` is
 * `final`, `readonly` and implements `ValueInterface`.
 */
final class ValueObjectContainmentTest extends TestCase
{
    private const VALUE_NAMESPACE = 'HeimrichHannot\\FlareBundle\\Filter\\Value\\';

    /** Directories that may declare `#[AsFilterElement]`. */
    private const ELEMENT_DIRS = ['src/Filter/Element', 'src/Integration/CodefogTags/FilterElement'];

    public function testEveryValueObjectIsFinalReadonlyAndImplementsValueInterface(): void
    {
        $classes = self::discoverValueClasses();

        self::assertNotSame([], $classes, 'No value objects found in src/Filter/Value/.');

        foreach (self::discoverElementClasses() as $element)
        {
            foreach ((new \ReflectionClass($element))->getAttributes(AsFilterElement::class) as $attribute)
            {
                $value = $attribute->getArguments()['value'] ?? null;

                if (\is_string($value)) {
                    $classes[] = $value;
                }
            }
        }

        foreach (\array_unique($classes) as $class)
        {
            self::assertTrue(\class_exists($class), \sprintf('Value class "%s" does not exist.', $class));

            $reflection = new \ReflectionClass($class);

            self::assertTrue($reflection->isFinal(), $class . ' must be final.');
            self::assertTrue($reflection->isReadOnly(), $class . ' must be readonly.');
            self::assertTrue(
                $reflection->implementsInterface(ValueInterface::class),
                $class . ' must implement ValueInterface.',
            );
        }
    }

    /**
     * @return list<class-string>
     */
    private static function discoverValueClasses(): array
    {
        $classes = [];

        foreach (\glob(self::projectDir() . '/src/Filter/Value/*.php') ?: [] as $file)
        {
            $class = self::VALUE_NAMESPACE . \basename($file, '.php');

            if ($class !== ValueInterface::class) {
                $classes[] = $class;
            }
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
}
