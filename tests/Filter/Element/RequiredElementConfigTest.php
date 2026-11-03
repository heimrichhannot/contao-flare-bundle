<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\Element;

use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Enum\SqlEquationOperator;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\Element\BelongsToRelationFilterElement;
use HeimrichHannot\FlareBundle\Filter\Element\BooleanFilterElement;
use HeimrichHannot\FlareBundle\Filter\Element\DateRangeFilterElement;
use HeimrichHannot\FlareBundle\Filter\Element\DcaSelectFieldFilterElement;
use HeimrichHannot\FlareBundle\Filter\Element\FieldValueChoiceFilterElement;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Element\SearchKeywordsFilterElement;
use HeimrichHannot\FlareBundle\Filter\Element\SimpleEquationFilterElement;
use HeimrichHannot\FlareBundle\Tests\Filter\BuildsFilterContextsTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Config an element needs in every case is required by its schema: a filter without it cannot be created, so the
 * list fails instead of matching nothing.
 */
final class RequiredElementConfigTest extends TestCase
{
    use BuildsFilterContextsTrait;

    /**
     * @dataProvider missingRequiredConfig
     *
     * @param class-string<FilterElementInterface> $class
     * @param array<string, mixed> $config
     */
    public function testMissingRequiredConfigIsRejectedBySchema(string $class, array $config): void
    {
        $this->expectException(FilterException::class);

        $this->buildElementScope($this->createElement($class), $config);
    }

    public static function missingRequiredConfig(): iterable
    {
        foreach ([
            BooleanFilterElement::class,
            DateRangeFilterElement::class,
            DcaSelectFieldFilterElement::class,
            FieldValueChoiceFilterElement::class,
        ] as $class) {
            $name = (new \ReflectionClass($class))->getShortName();

            yield "$name without field" => [$class, []];
            yield "$name with null field" => [$class, ['field' => null]];
            yield "$name with empty field" => [$class, ['field' => '']];
        }

        yield 'keywords without columns' => [SearchKeywordsFilterElement::class, []];
        yield 'keywords with empty columns' => [SearchKeywordsFilterElement::class, ['columns' => []]];

        yield 'equation without left' => [SimpleEquationFilterElement::class, ['operator' => SqlEquationOperator::EQUALS]];
        yield 'equation with empty left' => [
            SimpleEquationFilterElement::class,
            ['left' => '', 'operator' => SqlEquationOperator::EQUALS],
        ];
        yield 'equation without operator' => [SimpleEquationFilterElement::class, ['left' => 'id']];

        yield 'relation without parent field' => [BelongsToRelationFilterElement::class, []];
        yield 'relation with empty parent field' => [BelongsToRelationFilterElement::class, ['field_pid' => '']];
    }

    /**
     * @param class-string<FilterElementInterface> $class
     */
    private function createElement(string $class): FilterElementInterface
    {
        return match ($class) {
            FieldValueChoiceFilterElement::class => new $class($this->createMock(Connection::class)),
            BelongsToRelationFilterElement::class => new $class($this->createMock(TranslatorInterface::class)),
            default => new $class(),
        };
    }
}
