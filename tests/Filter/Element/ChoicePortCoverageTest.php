<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\Element;

use HeimrichHannot\FlareBundle\Contract\FilterElement\ChoiceSourceContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Filter\Value\ChoiceValue;
use PHPUnit\Framework\TestCase;

/**
 * Every bundle element consuming a {@see ChoiceValue} offers the choice port.
 */
final class ChoicePortCoverageTest extends TestCase
{
    public function testEveryChoiceElementImplementsTheChoicePort(): void
    {
        $choiceElements = [];

        foreach ($this->findElementClasses() as $class)
        {
            $reflection = new \ReflectionClass($class);

            foreach ($reflection->getAttributes(AsFilterElement::class) as $attribute)
            {
                if ($attribute->newInstance()->value !== ChoiceValue::class) {
                    continue;
                }

                $choiceElements[] = $reflection->getShortName();

                self::assertTrue(
                    $reflection->implementsInterface(ChoiceSourceContract::class),
                    \sprintf('%s declares value: ChoiceValue but does not implement the choice port.', $class),
                );
            }
        }

        \sort($choiceElements);

        self::assertSame([
            'ArchiveFilterElement',
            'CodefogTagsChoiceFilterElement',
            'DcaSelectFieldFilterElement',
            'FieldValueChoiceFilterElement',
        ], $choiceElements);
    }

    /**
     * @return list<class-string>
     */
    private function findElementClasses(): array
    {
        $src = \dirname(__DIR__, 3) . '/src';
        $files = \array_merge(
            \glob($src . '/Filter/Element/*.php') ?: [],
            \glob($src . '/Integration/*/FilterElement/*.php') ?: [],
        );

        $classes = [];

        foreach ($files as $file)
        {
            $class = 'HeimrichHannot\\FlareBundle\\'
                . \str_replace('/', '\\', \substr($file, \strlen($src) + 1, -\strlen('.php')));

            if (\class_exists($class)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }
}
